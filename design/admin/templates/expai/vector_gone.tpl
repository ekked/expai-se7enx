{* expai/vector/(thread)/<uuid> of a thread that no longer exists, or that the user may not open (the same answer):
   answered with HTTP 404 by modules/expai/vector.php, with a way back to Vector. *}
{ezcss_require( array( 'expai_vector.css' ) )}
{def $exp_ai_i18n = 'extension/expai/vector'}
<div class="exp-ai exp-ai-vector exp-ai-vector-gone">
    <header class="exp-ai-v-head">
        <div class="exp-ai-v-brand">
            <span class="exp-ai-mark" aria-hidden="true"></span>
            <h1>{'Vector'|i18n( $exp_ai_i18n )}</h1>
            <span class="exp-ai-v-brand-sub">{'Exponential Intelligence'|i18n( $exp_ai_i18n )}</span>
        </div>
    </header>
    <div class="exp-ai-v-notice" role="alert">
        <h2>{'This thread no longer exists'|i18n( $exp_ai_i18n )}</h2>
        <p>{'It was deleted, or you may not open it.'|i18n( $exp_ai_i18n )}</p>
        <p><a href={'expai/vector'|ezurl}>{'Back to Vector'|i18n( $exp_ai_i18n )}</a></p>
    </div>
</div>
{undef $exp_ai_i18n}
