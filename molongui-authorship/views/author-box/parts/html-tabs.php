<?php

use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) || exit;  


if ( !isset( $profile ) and isset( $profiles ) )
{
    $profile = $profiles;
}

$random_id = Helpers::rand();
$box_tabs  = array
(
    'name' => 'mab-tabs-'.$random_id,
    'tabs' => array
    (
        'profile' => array
        (
            'id'      => 'mab-tab-profile-'.$random_id,
            'label'   => apply_filters( 'authorship/box/profile/title', $options['author_box_profile_title'], $profile ), 
            'class'   => 'm-a-box-profile-title', 
            'checked' => true,
            'display' => true,
        ),
        'related' => array
        (
            'id'      => 'mab-tab-related-'.$random_id,
            'label'   => apply_filters( 'authorship/box/related/title', $options['author_box_related_title'], $profile ), 
            'class'   => 'm-a-box-related-title', 
            'checked' => false,
            'display' => $show_related,
        ),
        'contact' => array
        (
            'id'      => 'mab-tab-contact-'.$random_id,
            'class'   => 'm-a-box-contact-title', 
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

        // Loop through the siblings until the first <nav> is found.
        while (navElement)
        {
            if (navElement.tagName.toLowerCase() === 'nav')
            {
                //console.log('Found <nav>:', navElement);

                // Find the first element with 'm-a-box-tab-active' class within the <nav>.
                const activeTab = navElement.querySelector('.<?php echo $active_class; ?>');
                if (activeTab)
                {
                    //console.log('Found active tab:', activeTab);

                    // Reset current active tab.
                    activeTab.classList.remove( '<?php echo $active_class; ?>' );

                    // Switch active tab to current clicked tab.
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
