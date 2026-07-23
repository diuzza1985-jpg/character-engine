<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CommentReply extends Model
{
    protected $fillable = [
        'character_id', 'post_id', 'ig_media_id', 'ig_comment_id',
        'commenter_username', 'comment_text', 'reply_text', 'ig_reply_id', 'status',
    ];
    public function character()
    {
        return $this->belongsTo(Character::class);
    }
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
