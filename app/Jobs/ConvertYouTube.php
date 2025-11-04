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
            // Create a user-friendly error message
            $userFriendlyMessage = 'Failed to download the video. ';

            if ($e instanceof ProcessFailedException) {
                // Error was already handled and broadcast in processYouTubeDownload()
                // Just log and re-throw
                throw $e;
            } else {
                // Handle other types of exceptions
                $userFriendlyMessage .= 'Please try again later or contact support if the issue persists.';
            }

            Log::error('YouTube conversion failed', [
                'url' => $this->youtubeLink,
                'format' => $this->downloadFormat,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'exception_type' => get_class($e)
            ]);

            broadcast(new ConversionFailed($this->userId, $userFriendlyMessage, $this->youtubeLink));
            throw $e; // Re-throw to mark job as failed
        }
    }

    private function processYouTubeDownload()
    {

    $path_cookies=storage_path().'/cookies.txt';

    // Log cookie file status at the start
    $this->logCookieStatus($path_cookies);

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
  

    // Determine if we should use cookies based on file existence and validity
    $useCookies = $this->validateCookieFile($path_cookies);

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

    try {
        $conversionProcess->run();
    } catch (ProcessTimedOutException $e) {
        $errorMessage = 'YouTube download timed out after 300 seconds. The video may be too large or unavailable.';
        Log::error('yt-dlp conversion timed out', [
            'url' => $cleanUrl,
            'format' => $this->downloadFormat,
            'timeout' => 300
        ]);
        broadcast(new ConversionFailed($this->userId, $errorMessage, $this->youtubeLink));
        throw new \Exception($errorMessage);
    }

    if (!$conversionProcess->isSuccessful()) {
        $errorOutput = $conversionProcess->getErrorOutput();
        $stdOutput = $conversionProcess->getOutput();

        // Create a more user-friendly error message based on the error output
        $userFriendlyMessage = $this->parseYtDlpError($errorOutput, $stdOutput);

        Log::error('yt-dlp conversion failed', [
            'url' => $cleanUrl,
            'format' => $this->downloadFormat,
            'error_output' => $errorOutput,
            'std_output' => $stdOutput,
            'command' => $conversionProcess->getCommandLine()
        ]);

        broadcast(new ConversionFailed($this->userId, $userFriendlyMessage, $this->youtubeLink));
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

    /**
     * Validate cookie file existence and format
     */
    private function validateCookieFile($cookiePath)
    {
        if (!file_exists($cookiePath)) {
            Log::info('Cookie file not found, proceeding without cookies', [
                'expected_path' => $cookiePath
            ]);
            return false;
        }

        if (!is_readable($cookiePath)) {
            Log::warning('Cookie file exists but is not readable', [
                'path' => $cookiePath,
                'permissions' => substr(sprintf('%o', fileperms($cookiePath)), -4)
            ]);
            return false;
        }

        $cookieContent = file_get_contents($cookiePath);
        if (empty($cookieContent)) {
            Log::warning('Cookie file is empty', [
                'path' => $cookiePath
            ]);
            return false;
        }

        // Basic validation of Netscape cookie format
        if (!str_contains($cookieContent, '.youtube.com')) {
            Log::warning('Cookie file does not contain YouTube cookies', [
                'path' => $cookiePath,
                'content_preview' => substr($cookieContent, 0, 100)
            ]);
            return false;
        }

        Log::info('Valid cookie file found', [
            'path' => $cookiePath,
            'size' => filesize($cookiePath)
        ]);

        return true;
    }

    /**
     * Log cookie file status for debugging
     */
    private function logCookieStatus($cookiePath)
    {
        if (file_exists($cookiePath)) {
            $size = filesize($cookiePath);
            $content = file_get_contents($cookiePath);
            $lineCount = substr_count($content, "\n");
            $hasYoutubeCookies = str_contains($content, '.youtube.com');

            Log::info('Cookie file status', [
                'exists' => true,
                'path' => $cookiePath,
                'size' => $size,
                'line_count' => $lineCount,
                'has_youtube_cookies' => $hasYoutubeCookies,
                'is_readable' => is_readable($cookiePath),
                'content_preview' => substr($content, 0, 200)
            ]);
        } else {
            Log::info('Cookie file status', [
                'exists' => false,
                'path' => $cookiePath
            ]);
        }
    }

    /**
     * Parse yt-dlp error output and return user-friendly message
     */
    private function parseYtDlpError($errorOutput, $stdOutput = '')
    {
        $combinedOutput = $errorOutput . ' ' . $stdOutput;
        $lowercaseOutput = strtolower($combinedOutput);

        // Common error patterns and their user-friendly messages

        // Cookie-related errors
        if (strpos($lowercaseOutput, 'cookie') !== false &&
            (strpos($lowercaseOutput, 'unable to open') !== false ||
             strpos($lowercaseOutput, 'no such file') !== false ||
             strpos($lowercaseOutput, 'error loading') !== false)) {
            return 'Cookie file error. Please try uploading your YouTube cookies again using the "Import YouTube Cookies" button.';
        }

        if (strpos($lowercaseOutput, 'sign in') !== false ||
            strpos($lowercaseOutput, 'login required') !== false ||
            strpos($lowercaseOutput, 'authentication') !== false) {
            return 'Authentication required. Please upload your YouTube cookies to download this video.';
        }

        if (strpos($lowercaseOutput, 'video unavailable') !== false ||
            strpos($lowercaseOutput, 'private video') !== false ||
            strpos($lowercaseOutput, 'video has been removed') !== false) {
            return 'This video is unavailable, private, or has been removed from YouTube.';
        }

        if (strpos($lowercaseOutput, 'age-restricted') !== false ||
            strpos($lowercaseOutput, 'sign in to confirm your age') !== false) {
            return 'This video is age-restricted and cannot be downloaded.';
        }

        if (strpos($lowercaseOutput, 'copyright') !== false) {
            return 'This video cannot be downloaded due to copyright restrictions.';
        }

        if (strpos($lowercaseOutput, 'live stream') !== false ||
            strpos($lowercaseOutput, 'live event') !== false) {
            return 'Live streams cannot be downloaded. Please wait until the stream has ended.';
        }

        if (strpos($lowercaseOutput, 'network') !== false ||
            strpos($lowercaseOutput, 'connection') !== false ||
            strpos($lowercaseOutput, 'timeout') !== false) {
            return 'Network connection issue. Please check your internet connection and try again.';
        }

        if (strpos($lowercaseOutput, 'no video formats found') !== false ||
            strpos($lowercaseOutput, 'requested format not available') !== false) {
            return 'The requested video format is not available for this video.';
        }

        if (strpos($lowercaseOutput, 'unable to extract') !== false) {
            return 'Unable to extract video information. The video URL may be invalid or the video may be region-blocked.';
        }

        if (strpos($lowercaseOutput, '403') !== false) {
            return 'Access denied. This video may be region-blocked or require authentication.';
        }

        if (strpos($lowercaseOutput, '404') !== false) {
            return 'Video not found. Please check the URL and try again.';
        }

        // If no specific error pattern is found, return a generic message
        return 'Failed to download the video. This could be due to regional restrictions, video availability, or temporary server issues. Please try again later.';
    }
}
