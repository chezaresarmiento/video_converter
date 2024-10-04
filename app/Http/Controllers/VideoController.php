<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertYouTube;
use Inertia\Inertia;

use Illuminate\Http\Request;
use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    //
    public function store(Request $request){
        // dd($request->all());
        //get the video file extension
        $extension = $request->file('video')->getClientOriginalExtension();
        $video = $request->file('video');
        $videoName = time().'_'.$request->name.".".$extension;
        $video->move(public_path('videos'), $videoName);
        $new_extension=$request->video_format;


        $convertedName = time().'_'.$request->name.".".$new_extension;

        //convert the video using ffmpeg
        // $ffmpeg = "ffmpeg -i ".public_path('videos')."/".$videoName." -vf scale=640:360 ".public_path('videos')."/".$convertedName;

        $ffmpeg = "ffmpeg -i ".public_path('videos')."/".$videoName." -q:a 0 -map a ".public_path('videos')."/".$convertedName;

        shell_exec($ffmpeg);

        return Inertia::render('UploadVideo', [
            'videos' => [
                'video' => $videoName
            ]
        ]);
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

        // Delete the video record
        $download->delete();

        // Optionally, delete the file from storage if needed
        Storage::delete($download->file_path);

        // Return a response
        return redirect()->back()->with('message', 'Video deleted successfully.');
    }
}
