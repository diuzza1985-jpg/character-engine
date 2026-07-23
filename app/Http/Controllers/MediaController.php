<?php
namespace App\Http\Controllers;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
class MediaController extends Controller
{
    public function show(Post $post, int $index = 0)
    {
        $path = $post->media_urls[$index] ?? null;
        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path);
    }
}
