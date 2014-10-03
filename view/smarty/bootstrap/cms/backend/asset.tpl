{extends file="base/index"}

{block name="content_title" append}
    <div class="page-header">
        {if $asset->id}
            <h1>{$asset->name}
                <small>{translate key="title.asset.edit"}</small>
            </h1>
        {else}
            <h1>{translate key="title.asset.add"}</h1>
        {/if}
    </div>
{/block}

{block name="content_body" append}
    {include file="base/form.prototype"}
    <div class="row">
        <div class="col-md-7">
            {if $media}
                <iframe width="560" height="315" src="{$media->getEmbedUrl()}" frameborder="0" allowfullscreen></iframe>
            {else}
                <img src="{image src=$asset->thumbnail width=700 height=350 transformation="crop"}"/>
            {/if}
        </div>
        <div class="col-md-5">
            <form id="{$form->getId()}" class="form-horizontal" action="{$app.url.request}" method="POST" role="form"
                  enctype="multipart/form-data">
                <fieldset>
                    {call formRows form=$form}

                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <input type="submit" class="btn btn-default" value="{translate key="button.save"}"/>
                            {if $referer}
                                <a href="{$referer}" class="btn">{translate key="button.cancel"}</a>
                            {/if}
                        </div>
                    </div>
                </fieldset>
            </form>
        </div>
    </div>
{/block}