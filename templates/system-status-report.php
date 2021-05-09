<?php
/* @var array $report */
?>
<table class="wc_status_table widefat" cellspacing="0" id="wpi-status">
    <thead>
    <tr>
        <th colspan="3" data-export-label="WooCommerce Polylang Integration">
            <h2><?php esc_html_e( 'WooCommerce Polylang Integration', 'woocommerce-polylang-integration' ); ?></h2></th>
    </tr>
    </thead>
    <tbody>
	<?php
	foreach ( $report as $page ) {

		if ( $page['page_id'] ) {
			$_page_name = '<a href="' . esc_url( get_edit_post_link( $page['page_id'] ) ) . '" title="' . esc_attr( sprintf( __( 'Edit %s page', 'woocommerce-polylang-integration' ), $page['page_name'] ) ) . '">' . esc_html( $page['page_name'] ) . '</a>';
		} else {
			$_page_name = esc_html( $page['page_name'] );
		}
		?>
        <tr>
            <td data-export-label="<?php echo esc_attr( $page['page_name'] ); ?>">
				<?php echo $_page_name; ?>:
            </td>
            <td class="help">
				<?php echo wc_help_tip( $page['help'] ); ?>
            </td>
            <td>
				<?php if ( $page['is_error'] ) : ?>
                    <mark class="error"><span class="dashicons dashicons-warning"></span> <?php echo esc_html( $page['message'] ); ?></mark>
				<?php else : ?>
                    <mark class="yes"><span class="dashicons dashicons-yes"></span></mark>
				<?php endif; ?>
            </td>
        </tr>
		<?php
	}
	?>
    </tbody>
</table>
