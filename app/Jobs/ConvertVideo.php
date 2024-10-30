<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Events\ConversionCompleted; // Notify the user when done
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Download;

class ConvertVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $originalVideoName;
    public $newFormat;
    public $userId;

    /**
     * Create a new job instance.
     *
     * @param string $originalVideoName
     * @param string $newFormat
     * @param int $userId
     */
    public function __construct($originalVideoName, $newFormat, $userId)
    {
        $this->originalVideoName = $originalVideoName;
        $this->newFormat = $newFormat;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
{
    $inputPath = storage_path('source') . '/' . $this->originalVideoName;
    $outputPath = storage_path('app/public/converted').'/' . pathinfo($this->originalVideoName, PATHINFO_FILENAME). '.' . $this->newFormat;

    \Log::info('Input Path: ' . $outputPath);
    // Create FFmpeg process
    $ffmpeg = "ffmpeg -i {$inputPath} -f {$this->newFormat} {$outputPath} 2>&1";
   \Log::info('Running FFmpeg Command: ' . $ffmpeg);
    
    $conversionResult = shell_exec($ffmpeg);

    \Log::info('FFmpeg Output: ' . $conversionResult);

    // Check if file exists after conversion
    if (file_exists($outputPath)) {
        // Store information in database and notify user
        $url =Storage::url("converted/".pathinfo($this->originalVideoName, PATHINFO_FILENAME). '.' . $this->newFormat); // Get public URL for download

        // Fire the event to notify user of completion
        broadcast(new ConversionCompleted($this->userId, $url));

        //delete the original file
        unlink($inputPath);

        // Save the converted file info in the database
        Download::create([
            'user_id' => $this->userId,
            'video_title' => pathinfo($this->originalVideoName, PATHINFO_FILENAME). '.' . $this->newFormat,
            'youtube_link' => '',
            'file_path' => $url,
            'file_format' => $this->newFormat
        ]);
    } else {
        \Log::error('Conversion failed. Output file not found.');
        throw new \Exception('Conversion failed.');
    }
}

}
