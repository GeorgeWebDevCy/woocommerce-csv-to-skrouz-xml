<?php
/**
 * Admin page template.
 *
 * @var array<string, mixed> $settings
 * @var array<string, mixed> $report
 * @var array<string, string>|null $notice
 * @var string $report_state
 * @var string $settings_action_url
 * @var string $generate_action_url
 * @var string $clear_log_action_url
 * @var string $feed_url
 * @var string $xml_url
 * @var string $log_path
 * @var array<string, string>|null $backfill_notice
 */
?>
<div class="wrap sxffw-admin">
	<h1><?php esc_html_e( 'Skroutz XML Feed', 'skroutz-xml-feed-for-woocommerce' ); ?></h1>

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $backfill_notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'error' === $backfill_notice['type'] ? 'error' : 'success' ); ?> is-dismissible">
			<p><?php echo esc_html( $backfill_notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<div class="sxffw-toolbar">
		<div class="sxffw-toolbar__item">
			<span class="sxffw-label"><?php esc_html_e( 'Public feed URL', 'skroutz-xml-feed-for-woocommerce' ); ?></span>
			<code><?php echo esc_html( $feed_url ); ?></code>
			<p class="description"><?php esc_html_e( 'Use this URL in Skroutz. It sends no-cache headers and is the canonical feed endpoint.', 'skroutz-xml-feed-for-woocommerce' ); ?></p>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( $feed_url ); ?>" target="_blank" rel="noreferrer"><?php esc_html_e( 'Open Feed', 'skroutz-xml-feed-for-woocommerce' ); ?></a>
				<button type="button" class="button button-secondary sxffw-copy-feed-url" data-url="<?php echo esc_attr( $feed_url ); ?>"><?php esc_html_e( 'Copy Feed URL', 'skroutz-xml-feed-for-woocommerce' ); ?></button>
				<a class="button button-secondary" href="<?php echo esc_url( $xml_url ); ?>" target="_blank" rel="noreferrer"><?php esc_html_e( 'Open Cached XML (Debug)', 'skroutz-xml-feed-for-woocommerce' ); ?></a>
				<a class="button button-secondary" href="https://validator.skroutz.gr/" target="_blank" rel="noreferrer"><?php esc_html_e( 'Open Skroutz Validator', 'skroutz-xml-feed-for-woocommerce' ); ?></a>
			</p>
		</div>
		<div class="sxffw-toolbar__item">
			<span class="sxffw-label"><?php esc_html_e( 'Log file', 'skroutz-xml-feed-for-woocommerce' ); ?></span>
			<code><?php echo esc_html( $log_path ); ?></code>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $generate_action_url ); ?>"><?php esc_html_e( 'Generate Feed Now', 'skroutz-xml-feed-for-woocommerce' ); ?></a>
				<a class="button button-secondary" href="<?php echo esc_url( $clear_log_action_url ); ?>"><?php esc_html_e( 'Clear Log + Report', 'skroutz-xml-feed-for-woocommerce' ); ?></a>
			</p>
		</div>
	</div>

	<?php if ( ! empty( $report['summary'] ) ) : ?>
		<div class="sxffw-summary">
			<?php foreach ( $report['summary'] as $label => $count ) : ?>
				<div class="sxffw-card">
					<span class="sxffw-card__label"><?php echo esc_html( ucwords( str_replace( '_', ' ', $label ) ) ); ?></span>
					<strong class="sxffw-card__value"><?php echo esc_html( (string) $count ); ?></strong>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="description">
			<?php
			printf(
				/* translators: 1: generated date, 2: build state, 3: duration in milliseconds */
				esc_html__( 'Last build: %1$s. Cache state: %2$s. Build time: %3$s ms.', 'skroutz-xml-feed-for-woocommerce' ),
				esc_html( (string) $report['generated_at'] ),
				esc_html( $report_state ),
				esc_html( (string) $report['duration_ms'] )
			);
			?>
		</p>
		<div class="sxffw-status-legend">
			<p><strong><?php esc_html_e( 'Status guide:', 'skroutz-xml-feed-for-woocommerce' ); ?></strong></p>
			<p><?php esc_html_e( 'Ready means the product is exportable right now. Review means it can be exported, but you should inspect warnings. Needs fixes means the product is included in scope but blocked by validation errors. Excluded means the row is intentionally skipped from the XML.', 'skroutz-xml-feed-for-woocommerce' ); ?></p>
		</div>
		<div class="sxffw-cache-guidance">
			<p><strong><?php esc_html_e( 'Caching guidance:', 'skroutz-xml-feed-for-woocommerce' ); ?></strong></p>
			<p><?php esc_html_e( 'Give Skroutz the public feed URL above, not the cached XML file URL. The plugin invalidates its XML cache when WooCommerce product, stock, pricing, or settings data changes, and the public endpoint sends no-cache headers. If you use a page cache or CDN, exclude /skroutz-feed.xml from caching to avoid stale edge responses.', 'skroutz-xml-feed-for-woocommerce' ); ?></p>
		</div>
	<?php else : ?>
		<p><?php esc_html_e( 'No cached feed is available yet. Generate one to inspect problems and publish the XML file.', 'skroutz-xml-feed-for-woocommerce' ); ?></p>
	<?php endif; ?>

	<div class="sxffw-grid">
		<div class="sxffw-panel">
			<h2><?php esc_html_e( 'Plugin Settings', 'skroutz-xml-feed-for-woocommerce' ); ?></h2>
			<form action="<?php echo esc_url( $settings_action_url ); ?>" method="post">
				<?php settings_fields( 'sxffw_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sxffw-root-element"><?php esc_html_e( 'Root XML element', 'skroutz-xml-feed-for-woocommerce' ); ?></label></th>
						<td><input id="sxffw-root-element" name="<?php echo esc_attr( Skroutz_Xml_Feed_For_Woocommerce_Settings::OPTION_KEY ); ?>[root_element]" type="text" class="regular-text" value="<?php echo esc_attr( (string) $settings['root_element'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sxffw-default-manufacturer"><?php esc_html_e( 'Default manufacturer', 'skroutz-xml-feed-for-woocommerce' ); ?></label></th>
						<td><input id="sxffw-default-manufacturer" name="<?php echo esc_attr( Skroutz_Xml_Feed_For_Woocommerce_Settings::OPTION_KEY ); ?>[default_manufacturer]" type="text" class="regular-text" value="<?php echo esc_attr( (string) $settings['default_manufacturer'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sxffw-default-vat"><?php esc_html_e( 'Default VAT rate', 'skroutz-xml-feed-for-woocommerce' ); ?></label></th>
						<td><input id="sxffw-default-vat" name="<?php echo esc_attr( Skroutz_Xml_Feed_For_Woocommerce_Settings::OPTION_KEY ); ?>[default_vat_rate]" type="text" class="small-text" value="<?php echo esc_attr( (string) $settings['default_vat_rate'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sxffw-in-stock"><?php esc_html_e( 'In-stock availability label', 'skroutz-xml-feed-for-woocommerce' ); ?></label></th>
						<td><input id="sxffw-in-stock" name="<?php echo esc_attr( Skroutz_Xml_Feed_For_Woocommerce_Settings::OPTION_KEY ); ?>[in_stock_availability]" type="text" class="regular-text" value="<?php echo esc_attr( (string) $settings['in_stock_availability'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sxffw-out-of-stock"><?php esc_html_e( 'Out-of-stock availability label', 'skroutz-xml-feed-for-woocommerce' ); ?></label></th>
						<td><input id="sxffw-out-of-stock" name="<?php echo esc_attr( Skroutz_Xml_Feed_For_Woocommerce_Settings::OPTION_KEY ); ?>[out_of_stock_availability]" type="text" class="regular-text" value="<?php echo esc_attr( (string) $settings['out_of_stock_availability'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="sxffw-cache-ttl"><?php esc_html_e( 'Cache TTL (minutes)', 'skroutz-xml-feed-for-woocommerce' ); ?></label></th>
						<td><input id="sxffw-cache-ttl" name="<?php echo esc_attr( Skroutz_Xml_Feed_For_Woocommerce_Settings::OPTION_KEY ); ?>[cache_ttl_minutes]" type="number" min="5" max="1440" class="small-text" value="<?php echo esc_attr( (string) $settings['cache_ttl_minutes'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Include hidden products', 'skroutz-xml-feed-for-woocommerce' ); ?></th>
						<td><label><input name="<?php echo esc_attr( Skroutz_Xml_Feed_For_Woocommerce_Settings::OPTION_KEY ); ?>[include_hidden_products]" type="checkbox" value="1" <?php checked( ! empty( $settings['include_hidden_products'] ) ); ?>> <?php esc_html_e( 'Export products with catalog visibility set to hidden or search only.', 'skroutz-xml-feed-for-woocommerce' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable logging', 'skroutz-xml-feed-for-woocommerce' ); ?></th>
						<td><label><input name="<?php echo esc_attr( Skroutz_Xml_Feed_For_Woocommerce_Settings::OPTION_KEY ); ?>[enable_logging]" type="checkbox" value="1" <?php checked( ! empty( $settings['enable_logging'] ) ); ?>> <?php esc_html_e( 'Write informational feed events to the plugin log file.', 'skroutz-xml-feed-for-woocommerce' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Settings', 'skroutz-xml-feed-for-woocommerce' ) ); ?>
			</form>
		</div>

		<div class="sxffw-panel">
			<h2><?php esc_html_e( 'Products Needing Attention', 'skroutz-xml-feed-for-woocommerce' ); ?></h2>
			<?php if ( ! empty( $report['problem_products'] ) ) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product', 'skroutz-xml-feed-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Status', 'skroutz-xml-feed-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Issues', 'skroutz-xml-feed-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $report['problem_products'] as $product_report ) : ?>
							<tr>
								<td><?php echo esc_html( $product_report['name'] . ' (#' . $product_report['source_id'] . ')' ); ?></td>
								<td><?php echo esc_html( $product_report['status'] ); ?></td>
								<td>
									<?php foreach ( $product_report['issues'] as $issue ) : ?>
										<div><?php echo esc_html( strtoupper( $issue['severity'] ) . ': ' . $issue['message'] ); ?></div>
									<?php endforeach; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><?php esc_html_e( 'No included products currently have warnings or blocking errors.', 'skroutz-xml-feed-for-woocommerce' ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $report['excluded_products'] ) ) : ?>
				<h3><?php esc_html_e( 'Excluded Products', 'skroutz-xml-feed-for-woocommerce' ); ?></h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product', 'skroutz-xml-feed-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'skroutz-xml-feed-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $report['excluded_products'] as $product_report ) : ?>
							<tr>
								<td><?php echo esc_html( $product_report['name'] . ' (#' . $product_report['source_id'] . ')' ); ?></td>
								<td>
									<?php foreach ( $product_report['issues'] as $issue ) : ?>
										<div><?php echo esc_html( $issue['message'] ); ?></div>
									<?php endforeach; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>
