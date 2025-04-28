<?php

use Molongui\Authorship\Common\Modules\Settings;
use Molongui\Authorship\Wizard;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

$options      = Settings::get();
$wizard_steps = Wizard::get_step_count();
$wizard_step  = 0;
?>

<?php // todo: Find a better way to override common styles. ?>
<style>
.molongui-setup-wizard__logo
{
    width: 348px;
}
</style>

<!-- Step 0: Welcome -->
<div id="step-<?php echo esc_attr( $wizard_step ); ?>" class="molongui-setup-wizard__step active">

    <div class="molongui-setup-wizard__title">

        <div class="molongui-setup-wizard__track">
            <span><?php esc_html_e( "Welcome to the Molongui Authorship Setup Wizard!", 'molongui-authorship' ); ?></span>
        </div>

        <h2><?php esc_html_e( "Easily Manage and Display Post Authors", 'molongui-authorship' ); ?></h2>

        <div class="molongui-setup-wizard__subtitle"></div>

    </div>

    <div class="molongui-setup-wizard__description">

        <div class="molongui-setup-wizard__column">
            <p><?php esc_html_e( "This quick setup will help you customize how authors are displayed and managed on your site. Ready to begin?", 'molongui-authorship' ); ?></p>
            <p><?php esc_html_e( "You can customize the plugin further in the plugin settings page at any time.", 'molongui-authorship' ); ?></p>
            <p><?php esc_html_e( "Click \"Get Started\" to begin the setup and start enhancing your posts with author information.", 'molongui-authorship' ); ?></p>
        </div>

        <div class="molongui-setup-wizard__column">

        </div>

    </div>

    <div class="molongui-setup-wizard__nav">

        <button type="button" class="molongui-setup-wizard__button next primary">
            <?php esc_html_e( "Get Started", 'molongui-authorship' ); ?>
        </button>

    </div>

</div><!-- step 0 -->

<!-- Step 1: Enable the author box? -->
<?php $wizard_step++; ?>
<div id="step-<?php echo esc_attr( $wizard_step ); ?>" class="molongui-setup-wizard__step">

    <div class="molongui-setup-wizard__title">

        <div class="molongui-setup-wizard__track">
            <span>
                <?php
                /*! // translators: %1$s: Current wizard step. %2$s: Total wizard steps. */
                printf( esc_html_x( "Step %1\$s of %2\$s", 'Current step versus total number of wizard steps', 'molongui-authorship' ), $wizard_step, $wizard_steps );
                ?>
            </span>
        </div>

        <h2><?php esc_html_e( "Display Author Boxes on Posts?", 'molongui-authorship' ); ?></h2>

        <div class="molongui-setup-wizard__subtitle"></div>
    </div>

    <div class="molongui-setup-wizard__description">

        <div class="molongui-setup-wizard__column">
            <p>
                <?php esc_html_e( "Show an author box below your posts to highlight writers and build credibility. You can customize its appearance later.", 'molongui-authorship' ); ?>
            </p>
            <div class="toggle">
                <div class="button r" id="button-1">
                    <input type="checkbox" class="checkbox" name="author_box_enabled" checked>
                    <div class="knobs"></div>
                    <div class="layer"></div>
                </div>
                <div class="toggle__label">
                    <label>
                        <?php esc_html_e( "Enable author boxes", 'molongui-authorship' ); ?>
                    </label>
                    <div class="toggle__label--description">
                        <?php esc_html_e( "You can select which post types to include in the plugin settings.", 'molongui-authorship' ); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="molongui-setup-wizard__column">

        </div>

    </div>

    <div class="molongui-setup-wizard__nav">

        <button type="button" class="molongui-setup-wizard__button back">
            <?php esc_html_e( "Back", 'molongui-authorship' ); ?>
        </button>

        <button type="button" class="molongui-setup-wizard__button next primary">
            <?php esc_html_e( "Next", 'molongui-authorship' ); ?>
        </button>

    </div>

</div><!-- step 1 -->

<!-- Step 2: Enable guest authors? -->
<?php $wizard_step++; ?>
<div id="step-<?php echo esc_attr( $wizard_step ); ?>" class="molongui-setup-wizard__step">

    <div class="molongui-setup-wizard__title">

        <div class="molongui-setup-wizard__track">
            <span>
                <?php
                /*! // translators: %1$s: Current wizard step. %2$s: Total wizard steps. */
                printf( esc_html_x( "Step %1\$s of %2\$s", 'Current step versus total number of wizard steps', 'molongui-authorship' ), $wizard_step, $wizard_steps );
                ?>
            </span>
        </div>

        <h2><?php esc_html_e( "Add Guest or External Authors?", 'molongui-authorship' ); ?></h2>

        <div class="molongui-setup-wizard__subtitle"></div>
    </div>

    <div class="molongui-setup-wizard__description">

        <div class="molongui-setup-wizard__column">
            <p>
                <?php esc_html_e( "Want to credit authors who aren’t registered users? Enable this to assign posts to guest writers or contributors without WordPress accounts.", 'molongui-authorship' ); ?>
            </p>
            <div class="toggle">
                <div class="button r" id="button-1">
                    <input type="checkbox" class="checkbox" name="guest_author_enabled" checked>
                    <div class="knobs"></div>
                    <div class="layer"></div>
                </div>
                <div class="toggle__label">
                    <label>
                        <?php esc_html_e( "Allow non-WPUser authors", 'molongui-authorship' ); ?>
                    </label>
                    <div class="toggle__label--description">
                        <?php esc_html_e( "Useful for collaborative blogs or guest posts.", 'molongui-authorship' ); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="molongui-setup-wizard__column">

        </div>

    </div>

    <div class="molongui-setup-wizard__nav">

        <button type="button" class="molongui-setup-wizard__button back">
            <?php esc_html_e( "Back", 'molongui-authorship' ); ?>
        </button>

        <button type="button" class="molongui-setup-wizard__button next primary">
            <?php esc_html_e( "Next", 'molongui-authorship' ); ?>
        </button>

    </div>

</div><!-- step 2 -->

<?php $wizard_step++; ?>
<!-- Step 3: Enable co-authors? -->
<div id="step-<?php echo esc_attr( $wizard_step ); ?>" class="molongui-setup-wizard__step">

    <div class="molongui-setup-wizard__title">

        <div class="molongui-setup-wizard__track">
        <span>
            <?php
            /*! // translators: %1$s: Current wizard step. %2$s: Total wizard steps. */
            printf( esc_html_x( "Step %1\$s of %2\$s", 'Current step versus total number of wizard steps', 'molongui-authorship' ), $wizard_step, $wizard_steps );
            ?>
        </span>
        </div>

        <h2><?php esc_html_e( "Collaborative Posts?", 'molongui-authorship' ); ?></h2>

        <div class="molongui-setup-wizard__subtitle"></div>
    </div>

    <div class="molongui-setup-wizard__description">

        <div class="molongui-setup-wizard__column">
            <p>
                <?php esc_html_e( "Enable this to assign multiple authors to a single post—perfect for team-written content or co-authored articles.", 'molongui-authorship' ); ?>
            </p>
            <div class="toggle">
                <div class="button r" id="button-1">
                    <input type="checkbox" class="checkbox" name="co_authors_enabled" checked>
                    <div class="knobs"></div>
                    <div class="layer"></div>
                </div>
                <div class="toggle__label">
                    <label>
                        <?php esc_html_e( "Allow co-authors", 'molongui-authorship' ); ?>
                    </label>
                    <div class="toggle__label--description">
                        <?php esc_html_e( "Great for magazines or multi-author blogs.", 'molongui-authorship' ); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="molongui-setup-wizard__column">

        </div>

    </div>

    <div class="molongui-setup-wizard__nav">

        <button type="button" class="molongui-setup-wizard__button back">
            <?php esc_html_e( "Back", 'molongui-authorship' ); ?>
        </button>

        <button type="button" class="molongui-setup-wizard__button next primary">
            <?php esc_html_e( "Next", 'molongui-authorship' ); ?>
        </button>

    </div>

</div><!-- step 3 -->

<!-- Step 4: Go Pro -->
<?php $wizard_step++; ?>
<div id="step-<?php echo esc_attr( $wizard_step ); ?>" class="molongui-setup-wizard__step">

    <div class="molongui-setup-wizard__title">

        <div class="molongui-setup-wizard__track">
            <span>
                <?php esc_html_e( "Need more features?", 'molongui-authorship' ); ?>
            </span>
        </div>

        <h2><?php esc_html_e( "Need to Add Multiple Contributors to a Post?", 'molongui-authorship' ); ?></h2>

        <div class="molongui-setup-wizard__subtitle"></div>
    </div>

    <div class="molongui-setup-wizard__description">

        <div class="molongui-setup-wizard__column">
            <p><?php esc_html_e( "Upgrade to Pro to unlock additional features:", 'molongui-authorship' ); ?></p>
            <ul class="premium-features">
                <li><?php echo wp_kses_post( sprintf( __( "%sCustom Post Type support%s.", 'molongui-authorship' ), '<strong>', '</strong>' ) ); ?></li>
                <li><?php echo wp_kses_post( sprintf( __( "%sAuthor archive page for guest authors%s.", 'molongui-authorship' ), '<strong>', '</strong>' ) ); ?></li>
                <li><?php echo wp_kses_post( sprintf( __( "Handy %sshortcodes%s to display the author box, the post byline and any author information anywhere.", 'molongui-authorship' ), '<strong>', '</strong>' ) ); ?></li>
                <li><?php echo wp_kses_post( sprintf( __( "%sAuthor list%s. ", 'molongui-authorship' ), '<strong>', '</strong>' ) ); ?></li>
                <li><?php esc_html_e( "And Many More!", 'molongui-authorship' ); ?></li>
            </ul>
            <p>
                <?php
                /*! // translators: %1$s: <a> tag. %2$s: </a> tag. */
                echo wp_kses_post( sprintf( __( "Click %1\$shere%2\$s to learn more and upgrade today!", 'molongui-authorship' ), '<a href="'.esc_url( MOLONGUI_AUTHORSHIP_WEB ).'" target="_blank" class="upgrade">', '</a>' ) );
                ?>
            </p>
        </div>

        <div class="molongui-setup-wizard__column">

        </div>

    </div>

    <div class="molongui-setup-wizard__nav">

        <button type="button" class="molongui-setup-wizard__button back">
            <?php esc_html_e( "Back", 'molongui-authorship' ); ?>
        </button>

        <button type="button" class="molongui-setup-wizard__button next primary">
            <?php esc_html_e( "Next", 'molongui-authorship' ); ?>
        </button>

    </div>

</div><!-- step 4 -->

<!-- Finish -->
<?php $wizard_step++; ?>
<div id="step-<?php echo esc_attr( $wizard_step ); ?>" class="molongui-setup-wizard__step">

    <div class="molongui-setup-wizard__title">

        <div class="molongui-setup-wizard__track">
            <span>
                <?php esc_html_e( "Setup Complete!", 'molongui-authorship' ); ?>
            </span>
        </div>

        <h2><?php esc_html_e( "You're All Set! 🎉", 'molongui-authorship' ); ?></h2>

        <div class="molongui-setup-wizard__subtitle"></div>
    </div>

    <div class="molongui-setup-wizard__description">

        <div class="molongui-setup-wizard__column">
            <p><?php esc_html_e( "Here are a few next steps to help you get the most out of the plugin:", 'molongui-authorship' ); ?></p>
            <ul>
                <li><?php echo wp_kses_post( sprintf( __( "%sAssign Authors to Posts%s. Edit any post to add authors—their profiles will display elegantly in the byline and author box.", 'molongui-authorship' ), '<strong>', '</strong>' ) ); ?></li>
                <li><?php echo wp_kses_post( sprintf( __( "%sManage Non-WPUser Authors%s. Add guest authors or contributors without WordPress accounts (if enabled).", 'molongui-authorship' ), '<strong>', '</strong>' ) ); ?></li>
                <li><?php echo wp_kses_post( sprintf( __( "%sFine-Tune Plugin Settings%s. Head to the plugin settings page to explore more customization options and fine-tune your setup.", 'molongui-authorship' ), '<strong>', '</strong>' ) ); ?></li>
            </ul>
            <p><?php esc_html_e( "Click \"Finish\" to complete the setup and start using the plugin.", 'molongui-authorship' ); ?></p>
        </div>

        <div class="molongui-setup-wizard__column">

        </div>

    </div>

    <div class="molongui-setup-wizard__nav">

        <button type="button" class="molongui-setup-wizard__button back">
            <?php esc_html_e( "Back", 'molongui-authorship' ); ?>
        </button>

        <button type="button" class="molongui-setup-wizard__button finish primary">
            <?php esc_html_e( "Finish!", 'molongui-authorship' ); ?>
        </button>

    </div>

</div><!-- finish -->
