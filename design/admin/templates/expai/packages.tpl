{* expai/packages: content packages, a thin layer on the kernel's packages (upload, validation, what an install meets
   here, install as drafts). Variables: package (expAiPackageRecord) with kernel_package (eZPackage or false), report,
   provenance, queued; or packages, count, offset, limit. Never publishes. Format: doc/bc/6.0/package-json-format.md *}
<div class="exp-ai exp-ai-packages-page">
    <header class="exp-ai-head">
        <span class="exp-ai-mark" aria-hidden="true"></span>
        <div>
            <h1 class="context-title">{if is_set( $package )}{$package.name|wash}{else}{'Content packages'|i18n( 'extension/expai/package' )}{/if}</h1>
            <p class="exp-ai-sub">{'Exponential Intelligence'|i18n( 'extension/expai/package' )}</p>
        </div>
    </header>
{if $notice}<p class="exp-ai-notice" role="status">{$notice|wash}</p>{/if}
{if $error}<p class="exp-ai-error" role="alert">{$error|wash}</p>{/if}

{if is_set( $package )}
{def $v = first_set( $report.validation, false() )
     $dry = first_set( $report.dry_run, false() )
     $imp = first_set( $report.import, false() )}
    <section class="exp-ai-card" aria-labelledby="exp-ai-pkg-facts">
        <h2 id="exp-ai-pkg-facts">{'Package'|i18n( 'extension/expai/package' )}</h2>
        <dl class="exp-ai-figures">
            <div><dt>{'State'|i18n( 'extension/expai/package' )}</dt><dd><span class="exp-ai-pill exp-ai-pkg-{$package.state|wash}">{$package.state|wash}</span></dd></div>
            <div><dt>{'Format'|i18n( 'extension/expai/package' )}</dt><dd>{if eq( $package.format_version, 'json' )}JSON{else}{'Classic XML'|i18n( 'extension/expai/package' )}{/if}</dd></div>
            <div><dt>{'Objects'|i18n( 'extension/expai/package' )}</dt><dd>{$package.object_count|wash}</dd></div>
            <div><dt>{'Files'|i18n( 'extension/expai/package' )}</dt><dd>{$package.file_count|wash} ({$package.bytes|si( byte )})</dd></div>
            <div><dt>{'SHA-256'|i18n( 'extension/expai/package' )}</dt><dd><code title="{$package.checksum|wash}">{$package.checksum|shorten( 16 )|wash}</code></dd></div>
            <div><dt>{'Uploaded'|i18n( 'extension/expai/package' )}</dt><dd>{$package.created|l10n( 'shortdatetime' )}{if $package.user} &middot; {$package.user.login|wash}{/if}</dd></div>
            <div><dt>{'Written by AI'|i18n( 'extension/expai/package' )}</dt><dd>{if first_set( $provenance.ai_generated, false() )}{'yes'|i18n( 'extension/expai/package' )}{if first_set( $provenance.models, array() )|count} ({$provenance.models|implode( ', ' )|wash}){/if}{else}{'no'|i18n( 'extension/expai/package' )}{/if}</dd></div>
{if first_set( $provenance.tools, array() )|count}
            <div><dt>{'Tools'|i18n( 'extension/expai/package' )}</dt><dd>{$provenance.tools|implode( ', ' )|wash}</dd></div>
{/if}
{if first_set( $provenance.generator, '' )}
            <div><dt>{'Made by'|i18n( 'extension/expai/package' )}</dt><dd>{$provenance.generator|wash}</dd></div>
{/if}
        </dl>
{if first_set( $provenance.notes, '' )}<p>{$provenance.notes|wash}</p>{/if}
{if $kernel_package}
        <p><a href={concat( 'package/view/full/', $kernel_package.name, '/local' )|ezurl}>{'The package on Setup > Packages'|i18n( 'extension/expai/package' )}</a>
           &middot; <a href={concat( 'package/export/', $kernel_package.name, '/local/(format)/json' )|ezurl}>{'Download as JSON'|i18n( 'extension/expai/package' )}</a></p>
{/if}
    </section>

    <section class="exp-ai-card" aria-labelledby="exp-ai-pkg-validation">
        <h2 id="exp-ai-pkg-validation">{'Validation'|i18n( 'extension/expai/package' )}</h2>
{if $v}
        <p>{if $v.valid}<span class="exp-ai-pill exp-ai-pill-ok">{'valid'|i18n( 'extension/expai/package' )}</span>{else}<span class="exp-ai-pill exp-ai-pill-warn">{'not valid'|i18n( 'extension/expai/package' )}</span>{/if}
           {'%errors errors, %warnings warnings, checked %time'|i18n( 'extension/expai/package',, hash( '%errors', $v.errors|count, '%warnings', $v.warnings|count, '%time', $v.at|l10n( 'shortdatetime' ) ) )}</p>
{if $v.errors|count}<ul class="exp-ai-pkg-errors">{foreach $v.errors as $line max 50}<li>{$line|wash}</li>{/foreach}</ul>{/if}
{if $v.warnings|count}<details><summary>{'Warnings'|i18n( 'extension/expai/package' )} ({$v.warnings|count})</summary><ul>{foreach $v.warnings as $line max 50}<li>{$line|wash}</li>{/foreach}</ul></details>{/if}
{/if}
    </section>

{if $kernel_package}
    <section class="exp-ai-card" aria-labelledby="exp-ai-pkg-actions">
        <h2 id="exp-ai-pkg-actions">{'Import as drafts'|i18n( 'extension/expai/package' )}</h2>
        <p class="exp-ai-muted">{'An import only writes drafts, as you, with your permissions; classes, settings and design files of the package are not installed. Publishing the drafts is a separate step (approval workflows apply). Make a dry run first.'|i18n( 'extension/expai/package' )}{if gt( $package.object_count, $queue_above )} {'This package is imported in the job queue.'|i18n( 'extension/expai/package' )}{/if}</p>
        <form method="post" action={concat( 'expai/packages/(package)/', $package.uuid )|ezurl} class="exp-ai-pkg-form">
            <style>.exp-ai-pkg-form label {ldelim} display: block; margin: 0 0 .5em; {rdelim} .exp-ai-pkg-form input[type=text], .exp-ai-pkg-form select {ldelim} display: block; max-width: 100%; box-sizing: border-box; {rdelim}</style>
            <label>{'An object that exists already'|i18n( 'extension/expai/package' )}
                <select name="ExpAiExisting">
                    <option value="update">{'gets a new draft'|i18n( 'extension/expai/package' )}</option>
                    <option value="skip">{'is left alone'|i18n( 'extension/expai/package' )}</option>
                </select></label>
            <label>{'Parent of the package\'s top objects (node id, node remote id or path; default: the content root)'|i18n( 'extension/expai/package' )}
                <input type="text" name="ExpAiParent" size="30" value="" /></label>
            <div class="exp-ai-pkg-buttons">
                <input type="submit" class="button exp-ai-btn" name="ExpAiPackageValidateButton" value="{'Validate again'|i18n( 'extension/expai/package' )}" />
                <input type="submit" class="button exp-ai-btn" name="ExpAiPackageDryRunButton" value="{'Dry run'|i18n( 'extension/expai/package' )}" {if and( $v, $v.valid|not )}disabled="disabled"{/if} />
                <input type="submit" class="button exp-ai-btn exp-ai-btn-primary" name="ExpAiPackageImportButton" value="{'Import as drafts'|i18n( 'extension/expai/package' )}" {if or( and( $v, $v.valid|not ), eq( $package.state, 'importing' ) )}disabled="disabled"{/if} />
            </div>
        </form>
    </section>
{/if}

{if $dry}
    <section class="exp-ai-card" aria-labelledby="exp-ai-pkg-dry">
        <h2 id="exp-ai-pkg-dry">{'Dry run'|i18n( 'extension/expai/package' )} <small>{$dry.at|l10n( 'shortdatetime' )}</small></h2>
        <p>{foreach $dry.counts as $action => $n}<span class="exp-ai-pill">{$action|wash}: {$n|wash}</span> {/foreach}</p>
        <table class="list exp-ai-draft-table" cellspacing="0">
            <thead><tr><th>{'Object'|i18n( 'extension/expai/package' )}</th><th>{'Class'|i18n( 'extension/expai/package' )}</th><th>{'Action'|i18n( 'extension/expai/package' )}</th><th>{'Locations'|i18n( 'extension/expai/package' )}</th></tr></thead>
            <tbody>
{foreach $dry.objects as $row max 500}
                <tr>
                    <td><code>{$row.remote_id|shorten( 40 )|wash}</code><br /><small>{$row.name|wash}</small></td>
                    <td>{$row.class_identifier|wash}{if ne( $row.class, 'installed' )} <small>({$row.class|wash})</small>{/if}</td>
                    <td>{$row.action|wash}{if $row.exists}<br /><small>{'object %id'|i18n( 'extension/expai/package',, hash( '%id', $row.object_id ) )}</small>{/if}</td>
                    <td><small>{foreach $row.locations as $l}{if $l.parent_remote_id}{$l.parent_remote_id|shorten( 30 )|wash}{else}{'top'|i18n( 'extension/expai/package' )}{/if}: {$l.parent|wash}{if $l.node_of_other_object} &ndash; {'the node remote id is another object\'s'|i18n( 'extension/expai/package' )}{/if}<br />{/foreach}</small></td>
                </tr>
{/foreach}
            </tbody>
        </table>
    </section>
{/if}

{if $imp}
    <section class="exp-ai-card" aria-labelledby="exp-ai-pkg-import">
        <h2 id="exp-ai-pkg-import">{'Import'|i18n( 'extension/expai/package' )} <small>{$imp.at|l10n( 'shortdatetime' )}</small></h2>
{if $imp.queued}
{def $pr = first_set( $imp.progress, hash( 'percent', 0, 'total', 0, 'done', 0, 'active', 0 ) )}
        <p>{'Job group'|i18n( 'extension/expai/package' )}: <a href={concat( 'expqueue/group/', $imp.group )|ezurl}>{$imp.group|shorten( 13 )|wash}</a></p>
        {include uri='design:expai/parts/bar.tpl' value=$pr.percent max=100 level='ok' label='import'
                 text='%done of %total jobs ended (%percent %)'|i18n( 'extension/expai/package',, hash( '%done', sub( $pr.total, $pr.active ), '%total', $pr.total, '%percent', $pr.percent ) )}
{undef $pr}
{/if}
        <p>{foreach $imp.counts as $action => $n}<span class="exp-ai-pill">{$action|wash}: {$n|wash}</span> {/foreach}</p>
{if first_set( $imp.waiting, array() )|count}<p class="exp-ai-muted">{'%count drafts wait for a parent that is a draft of this package too: publish the parent, then import the package again.'|i18n( 'extension/expai/package',, hash( '%count', $imp.waiting|count ) )}</p>{/if}
{if first_set( $imp.skipped, array() )|count}<p class="exp-ai-muted">{'Not installed (only content is imported as drafts)'|i18n( 'extension/expai/package' )}: {foreach $imp.skipped as $s}{$s.type|wash} {$s.name|wash}{delimiter}, {/delimiter}{/foreach}</p>{/if}
{if $imp.results|count}
        <table class="list exp-ai-draft-table" cellspacing="0">
            <thead><tr><th>{'Object'|i18n( 'extension/expai/package' )}</th><th>{'Action'|i18n( 'extension/expai/package' )}</th><th>{'Draft'|i18n( 'extension/expai/package' )}</th></tr></thead>
            <tbody>
{foreach $imp.results as $r max 500}
                <tr>
                    <td><code>{$r.remote_id|shorten( 40 )|wash}</code><br /><small>{$r.name|wash}</small></td>
                    <td>{$r.action|wash}</td>
                    <td>{if $r.version}<a href={$r.url|ezurl}>{$r.object_id|wash}/{$r.version|wash}</a>{/if}</td>
                </tr>
{/foreach}
            </tbody>
        </table>
{/if}
    </section>
{/if}
{undef $v $dry $imp}
    <p><a href={'expai/packages'|ezurl}>{'All packages'|i18n( 'extension/expai/package' )}</a></p>

{else}
    <section class="exp-ai-card" aria-labelledby="exp-ai-pkg-upload">
        <h2 id="exp-ai-pkg-upload">{'Upload a package'|i18n( 'extension/expai/package' )}</h2>
        <p class="exp-ai-muted">{'A package is an .ezpkg file in the classic or the JSON format, up to %max. It is validated at once and kept on Setup > Packages; nothing is imported until you ask for it, and then only as drafts.'|i18n( 'extension/expai/package',, hash( '%max', $max_upload|si( byte ) ) )}
           <a href="https://github.com/se7enxweb/expai/blob/main/doc/packages.md">{'How packages work'|i18n( 'extension/expai/package' )}</a></p>
        <form method="post" action={'expai/packages'|ezurl} enctype="multipart/form-data">
            <input type="file" name="ExpAiPackageFile" accept=".ezpkg,.tgz,.gz,application/gzip,application/x-gzip" required="required" />
            <input type="submit" class="button exp-ai-btn exp-ai-btn-primary" name="ExpAiPackageUploadButton" value="{'Upload and validate'|i18n( 'extension/expai/package' )}" />
        </form>
    </section>

    <section class="exp-ai-card" aria-labelledby="exp-ai-pkg-list">
        <h2 id="exp-ai-pkg-list">{'Packages'|i18n( 'extension/expai/package' )} <small>({$count|wash})</small></h2>
{if $packages|count}
        <table class="list exp-ai-draft-table" cellspacing="0">
            <thead><tr><th>{'Name'|i18n( 'extension/expai/package' )}</th><th>{'State'|i18n( 'extension/expai/package' )}</th><th>{'Objects'|i18n( 'extension/expai/package' )}</th><th>{'Origin'|i18n( 'extension/expai/package' )}</th><th>{'Uploaded'|i18n( 'extension/expai/package' )}</th></tr></thead>
            <tbody>
{foreach $packages as $p}
                <tr><td><a href={concat( 'expai/packages/(package)/', $p.uuid )|ezurl}>{$p.name|wash}</a></td><td>{$p.state|wash}</td><td>{$p.object_count|wash}</td><td>{$p.origin|wash}</td><td>{$p.created|l10n( 'shortdatetime' )}</td></tr>
{/foreach}
            </tbody>
        </table>
        {include name=navigator uri='design:navigator/google.tpl' page_uri='expai/packages' item_count=$count view_parameters=hash( 'offset', $offset ) item_limit=$limit}
{else}
        <p class="exp-ai-muted">{'No package was uploaded yet.'|i18n( 'extension/expai/package' )}</p>
{/if}
    </section>
{/if}
</div>
