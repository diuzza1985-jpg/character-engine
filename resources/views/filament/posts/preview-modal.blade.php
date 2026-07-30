<div style="display:flex; flex-direction:column; gap:16px;">
    @if($post->media_urls)
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            @foreach($post->media_urls as $path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('local')->temporaryUrl($path, now()->addMinutes(30)) }}" style="max-width:280px; border-radius:8px;">
            @endforeach
        </div>
    @endif

    @if($post->caption)
        <div style="white-space:pre-wrap;">{{ $post->caption }}</div>
    @endif

    <dl style="display:grid; grid-template-columns:auto 1fr; gap:4px 12px; font-size:13px;">
        <dt style="font-weight:600;">Stato</dt>
        <dd>{{ $post->status }}</dd>
        <dt style="font-weight:600;">Tipo</dt>
        <dd>{{ $post->media_type }}{{ $post->narrative_format ? ' / ' . $post->narrative_format : '' }}</dd>
        @if($post->platform_post_id)
            <dt style="font-weight:600;">ID post Instagram</dt>
            <dd>{{ $post->platform_post_id }}</dd>
        @endif
        @if($post->published_at)
            <dt style="font-weight:600;">Pubblicato il</dt>
            <dd>{{ $post->published_at->format('d/m/Y H:i') }}</dd>
        @endif
    </dl>
</div>
