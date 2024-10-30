<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertYouTube;
use App\Jobs\ConvertVideo;
use Inertia\Inertia;

use Illuminate\Http\Request;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    //
    public function store(Request $request){

        // Validate the request
        // $request->validate([
        //     // 'video' => 'required|file|mimes:mp4,avi,mov', // Specify allowed formats
        //     'video_format' => 'required|in:mp4,avi,mov',
        //     'name' => 'required|string'
        // ]);

        

        // Get the original video
        $video = $request->file('video');
        $originalVideoName = time() . '_' . $request->name . '.' . $video->getClientOriginalExtension();
        $video->move(storage_path('source'), $originalVideoName);

        // Dispatch the job to convert the video
        ConvertVideo::dispatch($originalVideoName, $request->video_format, auth()->id());

        return redirect()->back()->with('message', 'The video is being converted. You will be notified once it is ready for download.');
    }

    public function download_video(Request $request){
        $request->validate([
            'youtubeLink' => 'required|url',
            'downloadFormat' => 'required|in:mp3,mp4,wav',
        ]);
    
        // Dispatch the job to the queue
        ConvertYouTube::dispatch($request->youtubeLink, $request->downloadFormat, auth()->id());
    
        // Respond to the client that the process has started
        return redirect()->back()->with('message', 'The video is being converted. You will be notified once it is ready for download.');
    }

    public function destroy($id)
    {
        // Find the download by ID
        $download = Download::findOrFail($id);

        // Delete the video record from the database
        $download->delete();

        //remove the word /storage/converted from the file path
        $file_path=trim(preg_replace('/storage\/converted/', '', $download->file_path),'/');

        $file_path=storage_path('app/public/converted/'.$file_path);

        if(file_exists($file_path)){
             // Delete the video file on the server
            unlink($file_path);
        }   

        // Return a response
        return redirect()->back()->with('message', 'Video deleted successfully.');
    }
}
