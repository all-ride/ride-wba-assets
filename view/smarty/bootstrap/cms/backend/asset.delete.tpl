{extends file="base/index"}

{block name="head_title" prepend}{/block}

{block name="content_title" append}
    <div class="page-header">
    </div>
{/block}

{block name="content_body" append}
    {include file="base/form.prototype"}

    <form action="{$app.url.request}" method="POST" role="form">
        <div class="form-group">
            <p>{translate key="label.confirm.delete"}</p>
        </div>

        <div class="form-group">
            <input type="submit" class="btn btn-danger" value="{translate key="button.delete"}" />
            {if $referer}
                <a class="btn" href="{$referer}">{translate key="button.cancel"}</a>
            {/if}
        </div>
    </form>
{/block}
