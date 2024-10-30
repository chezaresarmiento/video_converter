<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Events\ConversionCompleted; // Use an event to notify when done
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Download;
use Symfony\Component\Process\Exception\ProcessFailedException;


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
    // Step 1: Get the video metadata (including title)
    $metadataProcess = new Process([
        'yt-dlp', '--print', 'title', $this->youtubeLink
    ]);
    $metadataProcess->run();

    if (!$metadataProcess->isSuccessful()) {
        throw new ProcessFailedException($metadataProcess);
    }

    // Get the title of the video
    $videoTitle = trim($metadataProcess->getOutput());

    // Sanitize the title (remove any invalid characters for file name)
    $videoTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', $videoTitle);
    $videoTitle = substr($videoTitle, 0, 20); // Limit the title to 100 characters
    //to lower case
    $videoTitle = strtolower($videoTitle);

    // Step 2: Define the output file path using the video title
    $outputFile = storage_path('app/public/converted').'/' . $videoTitle . '.' . $this->downloadFormat;
    $path_cookies=storage_path().'/cookies.txt';

    if($this->downloadFormat=='mp3'){
        // Step 3: Convert YouTube video to MP3
        $conversionProcess = new Process([
            "yt-dlp --cookies $path_cookies", '-x', '--audio-format', $this->downloadFormat,
            '-o', $outputFile,
            $this->youtubeLink
        ]);
    }

    if($this->downloadFormat=='mp4'){
        $conversionProcess = new Process([
            'yt-dlp', '-f', 'bestvideo[ext=mp4]+bestaudio[ext=m4a]', // Best video in MP4 and best audio
            '--postprocessor-args', '-c:v libx264 -c:a aac', // Ensure video is H.264 and audio is AAC (QuickTime-friendly)
            '--merge-output-format', 'mp4', // Merge into MP4 format
            '-o', $outputFile, // Output file location
            $this->youtubeLink
        ]);
    }
    

    $conversionProcess->setTimeout(300); // Set the timeout to 300 seconds
    $conversionProcess->run();

    if (!$conversionProcess->isSuccessful()) {
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
    } else {
        throw new \Exception('Conversion failed: ' . $conversionProcess->getErrorOutput());
    }
}

}
