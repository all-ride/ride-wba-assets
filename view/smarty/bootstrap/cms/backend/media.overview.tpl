{extends file="base/index"}

{block name="head_title" prepend}{translate key="title.media"} - {/block}

{block name="taskbar_panels" append}
    {url id="media.album.overview" parameters=["locale" => "%locale%", "album" => $album->id] var="url"}
    {call taskbarPanelLocales url=$url locale=$locale locales=$locales}
{/block}

{block name="content_title" append}
    <div class="page-header">
        <h1>{translate key="title.media"}</h1>
    </div>
{/block}

{block name="content_body" append}
    {include file="base/form.prototype"}
    <form action="{$app.url.request}" method="POST" id="{$form->getId()}" class="form-horizontal">
        {call formRow form=$form row="album"}
    </form>

    <div class="btn-group media-actions">
        <a href="{url id="media.album.add" parameters=["locale" => $locale]}?album={$album->id}" class="btn btn-default btn-small">{translate key="button.add.album"}</a>
        <a href="{url id="media.item.add" parameters=["locale" => $locale]}?album={$album->id}" class="btn btn-default btn-small">{translate key="button.add.media"}</a>
    </div>

    {if $album->description}
        <div class="description">{$album->description}</div>
    {/if}
<div class="row-fluid">
    <div class="col-md-6 media-items">
        <h3>{translate key="title.albums"}</h3>
{if $album->children}
    {foreach $album->children as $child}
        <div class="row-fluid media-item" id="album-{$child->id}">
            <div class="media-handle media-album"></div>
            <a href="{url id="media.album.overview" parameters=["locale" => $locale, "album" => $child->id]}">{$child->name}</a>
            {$child->description}
            <div class="info text-muted">
                {translate key="label.album.info" albums=count($child->children) items=count($child->media)}
            </div>
            <div class="btn-group">
                <a href="{url id="media.album.edit" parameters=["locale" => $locale, "album" => $child->id]}" class="btn btn-default btn-sm">{translate key="button.edit"}</a>
                <a href="{url id="media.album.delete" parameters=["locale" => $locale, "album" => $child->id]}" class="btn btn-default btn-sm btn-confirm" data-message="Are you sure you want to delete {$child->name|escape}?">{translate key="button.delete"}</a>
            </div>
            <hr />
        </div>
    {/foreach}
{else}
    <p>There are no subalbums in the current album.</p>
{/if}
    </div>

    <div class="col-md-6 media-items">
        <h3>Media</h3>
{if $album->media}
    {foreach $album->media as $media}
        <div class="row-fluid media-item clearfix" id="item-{$media->id}">
            <div class="clearfix">
                <div class="media-handle media-{$media->type}"></div>
                {if $media->thumbnail}
                <div class="image">
                    {image src=$media->thumbnail thumbnail="crop" width=100 height=100}
                </div>
                {elseif $media->type == 'image'}
                <div class="image">
                    {image src=$media->value thumbnail="crop" width=100 height=100}
                </div>
                {/if}
                <div>
                    <a href="{url id="media.item.edit" parameters=["locale" => $locale, "item" => $media->id]}">{$media->name}</a>
                    {$media->description}
                </div>
                <div class="btn-group">
                    <a href="{url id="media.item.edit" parameters=["locale" => $locale, "item" => $media->id]}" class="btn btn-default btn-sm">{translate key="button.edit"}</a>
                    <a href="{url id="media.item.delete" parameters=["locale" => $locale, "item" => $media->id]}" class="btn btn-default btn-sm btn-confirm" data-message="Are you sure you want to delete {$media->name|escape}?">{translate key="button.delete"}</a>
                </div>
            </div>
            <hr />
        </div>
    {/foreach}
{else}
    <p>There is no media in the current album.</p>
{/if}
    </div>
</div>
{/block}

{block name="styles" append}
    <link href="{$app.url.base}/css/cms/media.css" rel="stylesheet" media="screen">
{/block}

{block name="scripts" append}
    <script src="{$app.url.base}/js/jquery-ui.js"></script>
    <script type="text/javascript">
        $(function() {
            $("#form-album").change(function() {
                $(this).parentsUntil("form").parent().submit();
            });

            $(".btn-confirm").click(function() {
                return confirm($(this).data('message'));
            });

            var sortUrl = "{url id="media.album.sort" parameters=["locale" => $locale, "album" => $album->id]}";

            $(".media-items").sortable({
                axis: "y",
                cursor: "move",
                handle: ".media-handle",
                items: "> .media-item",
                select: false,
                scroll: true,
                update: function(event, ui) {
                    $.post(sortUrl + '?' + $(ui.item).parent().sortable('serialize'));
                }
            }).disableSelection();
        });
    </script>
{/block}
