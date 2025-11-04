<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Events\ConversionCompleted; // Use an event to notify when done
use App\Events\ConversionFailed;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Download;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;


class ConvertYouTube implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $youtubeLink;
    public $downloadFormat;
    public $userId;

    /**
     * Create a new job instance.
     *
     * @param string $youtubeLink
     * @param string $downloadFormat
     * @param int $userId
     */
    public function __construct($youtubeLink, $downloadFormat, $userId)
    {
        $this->youtubeLink = $youtubeLink;
        $this->downloadFormat = $downloadFormat;
        $this->userId = $userId;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $this->processYouTubeDownload();
        } catch (\Exception $e) {
            Log::error('YouTube conversion failed', [
                'url' => $this->youtubeLink,
                'format' => $this->downloadFormat,
                'user_id' => $this->userId,
                'error' => $e->getMessage()
            ]);

            broadcast(new ConversionFailed($this->userId, $e->getMessage(), $this->youtubeLink));
            throw $e; // Re-throw to mark job as failed
        }
    }

    private function processYouTubeDownload()
    {
   
    $path_cookies=storage_path().'/cookies.txt';

    // Clean the YouTube URL to remove playlist parameters
    $cleanUrl = $this->cleanYouTubeUrl($this->youtubeLink);

    // Step 1: Get the video metadata (including title)
    $videoTitle = null;

    try {
        // Try with cookies first (shorter timeout to fail fast)
        $metadataProcess = new Process([
            'yt-dlp',"--cookies",$path_cookies, '--no-playlist', '--socket-timeout', '30', '--print', 'title', $cleanUrl
        ]);
        $metadataProcess->setTimeout(60); // Set shorter timeout for cookies attempt
        $metadataProcess->run();

        if ($metadataProcess->isSuccessful()) {
            $videoTitle = trim($metadataProcess->getOutput());
        }
    } catch (ProcessTimedOutException $e) {
        Log::warning('yt-dlp with cookies timed out', [
            'timeout' => 60,
            'url' => $cleanUrl
        ]);
    }

    // If cookies method failed or timed out, try without cookies as fallback
    if (empty($videoTitle)) {
        try {
            Log::warning('yt-dlp with cookies failed, trying without cookies', [
                'url' => $cleanUrl
            ]);

            $metadataProcess = new Process([
                'yt-dlp', '--no-playlist', '--socket-timeout', '30', '--print', 'title', $cleanUrl
            ]);
            $metadataProcess->setTimeout(90); // Moderate timeout for no-cookies attempt
            $metadataProcess->run();

            if ($metadataProcess->isSuccessful()) {
                $videoTitle = trim($metadataProcess->getOutput());
            }
        } catch (ProcessTimedOutException $e) {
            Log::warning('yt-dlp without cookies also timed out', [
                'timeout' => 90,
                'url' => $cleanUrl
            ]);
        }
    }

    // If both methods failed, use a generic title based on video ID
    if (empty($videoTitle)) {
        $videoId = $this->extractVideoId($cleanUrl);
        $videoTitle = 'youtube_video_' . $videoId;
        Log::warning('Both cookie and no-cookie methods failed, using generic title', [
            'url' => $cleanUrl,
            'generic_title' => $videoTitle
        ]);
    }

    // videoTitle is already set in the logic above

    // Sanitize the title (remove any invalid characters for file name)
    $videoTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', $videoTitle);
    $videoTitle = substr($videoTitle, 0, 20); // Limit the title to 100 characters
    //to lower case
    $videoTitle = strtolower($videoTitle);

    // Step 2: Define the output file path using the video title
    $outputFile = storage_path('app/public/converted').'/' . $videoTitle . '.' . $this->downloadFormat;
  

    // Determine if we should use cookies based on metadata success
    $useCookies = file_exists($path_cookies);

    if($this->downloadFormat=='mp3'){
        // Step 3: Convert YouTube video to MP3
        if ($useCookies) {
            $conversionProcess = new Process([
                "yt-dlp","--cookies",$path_cookies, '--no-playlist', "-x", '--audio-format', $this->downloadFormat,
                '-o', $outputFile,
                $cleanUrl
            ]);
        } else {
            $conversionProcess = new Process([
                "yt-dlp", '--no-playlist', "-x", '--audio-format', $this->downloadFormat,
                '-o', $outputFile,
                $cleanUrl
            ]);
        }
    }

    if($this->downloadFormat=='mp4'){
        if ($useCookies) {
            $conversionProcess = new Process([
                'yt-dlp',"--cookies",$path_cookies, '--no-playlist', '-f', 'bestvideo[ext=mp4]+bestaudio[ext=m4a]', // Best video in MP4 and best audio
                '--postprocessor-args', '-c:v libx264 -c:a aac', // Ensure video is H.264 and audio is AAC (QuickTime-friendly)
                '--merge-output-format', 'mp4', // Merge into MP4 format
                '-o', $outputFile, // Output file location
                $cleanUrl
            ]);
        } else {
            $conversionProcess = new Process([
                'yt-dlp', '--no-playlist', '-f', 'bestvideo[ext=mp4]+bestaudio[ext=m4a]', // Best video in MP4 and best audio
                '--postprocessor-args', '-c:v libx264 -c:a aac', // Ensure video is H.264 and audio is AAC (QuickTime-friendly)
                '--merge-output-format', 'mp4', // Merge into MP4 format
                '-o', $outputFile, // Output file location
                $cleanUrl
            ]);
        }
    }
    

    $conversionProcess->setTimeout(300); // Set the timeout to 300 seconds
    $conversionProcess->run();

    if (!$conversionProcess->isSuccessful()) {
        $errorMessage = 'Video conversion failed: ' . $conversionProcess->getErrorOutput();
        throw new ProcessFailedException($conversionProcess);
    }
    // Store download information in the database
    

    // Step 4: Notify the user when conversion is done
    if ($conversionProcess->isSuccessful()) {
        $url = Storage::url('converted'.'/'.$videoTitle . '.' . $this->downloadFormat); // Get the public URL for the converted file
        broadcast(new ConversionCompleted($this->userId, $url)); // Fire the event to notify user
        Download::create([
            'user_id' => $this->userId,
            'video_title' => $videoTitle,
            'youtube_link' => $this->youtubeLink,
            'file_path' => $url,
            'file_format' => $this->downloadFormat,
        ]);
    }
}

    /**
     * Clean YouTube URL to remove playlist parameters
     */
    private function cleanYouTubeUrl($url)
    {
        // Remove playlist parameters that cause yt-dlp to fetch entire playlists
        $cleanUrl = preg_replace('/[&?]list=[^&]*/', '', $url);
        $cleanUrl = preg_replace('/[&?]start_radio=[^&]*/', '', $cleanUrl);
        $cleanUrl = preg_replace('/[&?]index=[^&]*/', '', $cleanUrl);

        return $cleanUrl;
    }

    /**
     * Extract video ID from YouTube URL
     */
    private function extractVideoId($url)
    {
        preg_match('/(?:v=|\/embed\/|\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $url, $matches);
        return isset($matches[1]) ? $matches[1] : 'unknown';
    }
}
