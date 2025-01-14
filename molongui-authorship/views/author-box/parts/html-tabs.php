<?php

use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
$random_id = Helpers::rand();
$box_tabs  = array
(
    'name' => 'mab-tabs-'.$random_id,
    'tabs' => array
    (
        'profile' => array
        (
            'id'      => 'mab-tab-profile-'.$random_id,
            'label'   => apply_filters( 'authorship/box/profile/title', $options['author_box_profile_title'], $profile ),//( $options['author_box_profile_title'] ? $options['author_box_profile_title'] : __( "About the author", 'molongui-authorship' ) ),
            'class'   => 'm-a-box-profile-title',//'m-a-box-string-about-the-author',
            'checked' => true,
            'display' => true,
        ),
        'related' => array
        (
            'id'      => 'mab-tab-related-'.$random_id,
            'label'   => apply_filters( 'authorship/box/related/title', $options['author_box_related_title'], $profile ),//( $options['author_box_related_title'] ? $options['author_box_related_title'] : __( "Related posts", 'molongui-authorship' ) ),
            'class'   => 'm-a-box-related-title',//'m-a-box-string-related-posts',
            'checked' => false,
            'display' => $show_related,
        ),
        'contact' => array
        (
            'id'      => 'mab-tab-contact-'.$random_id,
            'class'   => 'm-a-box-contact-title',//'m-a-box-string-contact',
            'checked' => false,
            'display' => false,
        ),
    ),
);
if ( !empty( $options['author_box_tabs_position'] ) ) $position = explode('-', $options['author_box_tabs_position'] );
$active_class = 'm-a-box-tab-active';
?>

<script type="text/javascript">
	function molonguiHandleTab(inputElement)
	{
        let navElement = inputElement.nextElementSibling;
        while (navElement)
        {
            if (navElement.tagName.toLowerCase() === 'nav')
            {
                const activeTab = navElement.querySelector('.<?php echo $active_class; ?>');
                if (activeTab)
                {
                    activeTab.classList.remove( '<?php echo $active_class; ?>' );
                    navElement.querySelector('label[for='+inputElement.id+']').classList.add( '<?php echo $active_class; ?>' );
                }

                break; // Stop once the first <nav> is found
            }

            navElement = navElement.nextElementSibling;
        }
	}
</script>

<?php
foreach ( $box_tabs['tabs'] as $box_tab ) :
    if ( !$box_tab['display'] ) continue; ?>
    <input type="radio" id="<?php echo $box_tab['id']; ?>" name="<?php echo $box_tabs['name']; ?>" onclick="molonguiHandleTab(this);" <?php echo ( $box_tab['checked'] ? 'checked' : '' ); ?>>
<?php endforeach; ?>

<nav>
    <?php foreach ( $box_tabs['tabs'] as $box_tab )
    {
        if ( !$box_tab['display'] ) continue;
        ?>
            <label for="<?php echo esc_attr( $box_tab['id'] ); ?>" class="m-a-box-tab <?php echo ( $box_tab['checked'] ? ' '.$active_class : '' ); ?>">
                <span class="<?php echo esc_attr( $box_tab['class'] ); ?>"><?php echo esc_html( $box_tab['label'] ); ?></span>
            </label>
        <?php
    }?>
</nav>
