{extends file="base/index"}

{block name="head_title" prepend}{translate key="title.asset"} - {/block}

{block name="taskbar_panels" append}
    {url id="assets.folder.overview" parameters=["locale" => "%locale%", "folder" => $folder->id] var="url"}
    {call taskbarPanelLocales url=$url locale=$locale locales=$locales}
{/block}

{block name="content_title" append}
    <div class="page-header">
        <h1>{translate key="title.assets"}</h1>
    </div>
{/block}

{block name="content_body" append}
    <div class="breadcrumbs">
        <ol class="breadcrumb">
            <li>
                <a href="{url id="assets.folder.overview" parameters=["locale" => $locale, "folder" => '']}">{translate key="label.assets"}</a>
            </li>
            {foreach $breadcrumbs as $id => $name}
                <li>
                    <a href="{url id="assets.folder.overview" parameters=["locale" => $locale, "folder" => $id]}">{$name}</a>
                </li>
            {/foreach}
        </ol>
    </div>
    {include file="base/form.prototype"}
    <div class="folder-top">
        <div class="btn-group asset-actions">
            <a href="{url id="assets.folder.add" parameters=["locale" => $locale]}?folder={$folder->id}"
               class="btn btn-default btn-small">{translate key="button.add.folder"}</a>
            <a href="{url id="asset.add" parameters=["locale" => $locale]}?folder={$folder->id}"
               class="btn btn-default btn-small">{translate key="button.add.asset"}</a>
        </div>
        {if $folder->description}
            <div class="description">{$folder->description}</div>
        {/if}
    </div>
    <div class="row-fluid">
        <div class="col-md-12 assets">
            {if $folder->children}
                {foreach $folder->children as $child}
                    <div class="row-fluid asset-item" id="folder-{$child->id}">
                        <div class="asset-handle asset-folder"></div>
                        <a href="{url id="assets.folder.overview" parameters=["locale" => $locale, "folder" => $child->id]}">{$child->name}</a>
                        <img src="{$app.url.base}/img/cms/media/folder.svg" />
                        <div class="btn-group">
                            <a href="{url id="assets.folder.edit" parameters=["locale" => $locale, "folder" => $child->id]}"
                               class="btn btn-default btn-sm">{translate key="button.edit"}</a>
                            <a href="{url id="assets.folder.delete" parameters=["locale" => $locale, "folder" => $child->id]}"
                               class="btn btn-default btn-sm btn-confirm"
                               data-message="Are you sure you want to delete {$child->name|escape}?">{translate key="button.delete"}</a>
                        </div>
                        <hr/>
                    </div>
                {/foreach}
            {/if}
            {if $folder->assets}
                {foreach $folder->assets as $asset}
                    <div class="row-fluid assets clearfix" id="item-{$asset->id}">
                        <div class="clearfix">
                            <div class="asset-handle assets-{$asset->type}"></div>
                            {if $asset->thumbnail}
                                <div class="image">
                                    <img src="{image src=$asset->thumbnail width=125 height=125 transformation="crop"}" />
                                </div>
                            {elseif $asset->type == 'image'}
                                {*<div class="image">*}
                                    {*{image src=$asset->value width=100 height=100}*}
                                {*</div>*}
                            {/if}
                            <div>
                                <a href="{url id="asset.edit" parameters=["locale" => $locale, "item" => $asset->id]}">{$asset->name}</a>
                                {$asset->description}
                            </div>
                            <div class="btn-group">
                                <a href="{url id="asset.edit" parameters=["locale" => $locale, "item" => $asset->id]}"
                                   class="btn btn-default btn-sm">{translate key="button.edit"}</a>
                                <a href="{url id="asset.delete" parameters=["locale" => $locale, "item" => $asset->id]}"
                                   class="btn btn-default btn-sm btn-confirm"
                                   data-message="Are you sure you want to delete {$asset->name|escape}?">{translate key="button.delete"}</a>
                            </div>
                        </div>
                        <hr/>
                    </div>
                {/foreach}
            {/if}
        </div>
    </div>
{/block}

{block name="styles" append}
    <link href="{$app.url.base}/css/cms/assets.css" rel="stylesheet" media="screen">
{/block}

{block name="scripts" append}
    <script src="{$app.url.base}/js/jquery-ui.js"></script>
    <script type="text/javascript">
        $(function () {
            $("#form-folder").change(function () {
                $(this).parentsUntil("form").parent().submit();
            });

            $(".btn-confirm").click(function () {
                return confirm($(this).data('message'));
            });

            var sortUrl = "{url id="assets.folder.sort" parameters=["locale" => $locale, "folder" => $folder->id]}";

            $(".assets").sortable({
                axis: "y",
                cursor: "move",
                handle: ".asset-handle",
                items: "> .asset",
                select: false,
                scroll: true,
                update: function (event, ui) {
                    $.post(sortUrl + '?' + $(ui.item).parent().sortable('serialize'));
                }
            }).disableSelection();
        });
    </script>
{/block}
