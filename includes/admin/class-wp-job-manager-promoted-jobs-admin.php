<?php
/**
 * File containing the class WP_Job_Manager_Promoted_Jobs.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles promoted jobs functionality.
 *
 * @since $$next-version$$
 */
class WP_Job_Manager_Promoted_Jobs_Admin {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  $$next-version$$
	 */
	private static $instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  $$next-version$$
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'manage_edit-job_listing_columns', [ $this, 'promoted_jobs_columns' ] );
		add_action( 'manage_job_listing_posts_custom_column', [ $this, 'promoted_jobs_custom_columns' ], 2 );
		add_action( 'admin_footer', [ $this, 'promoted_jobs_admin_footer' ] );
		add_action( 'load-edit.php', [ $this, 'handle_deactivate_promotion' ] );
		add_action( 'wpjm_job_listing_bulk_actions', [ $this, 'add_action_notice' ] );

	}

	/**
	 * Add a column to the job listings admin page.
	 *
	 * @param array $columns Columns.
	 *
	 * @return array
	 */
	public function promoted_jobs_columns( $columns ) {
		$columns['promoted_jobs'] = __( 'Promote', 'wp-job-manager' );

		return $columns;
	}

	/**
	 * Handle request to deactivate promotion for a job.
	 */
	public function handle_deactivate_promotion() {
		if ( ! isset( $_GET['action'] ) || 'deactivate_promotion' !== $_GET['action'] ) {
			return;
		}

		$post_id = absint( $_GET['post_id'] ?? 0 );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce should not be modified.
		if ( ! $post_id || empty( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'deactivate_promotion_' . $_GET['post_id'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_job_listings', $post_id ) || 'job_listing' !== get_post_type( $post_id ) ) {
			return;
		}

		$this->deactivate_promotion( $post_id );

		wp_safe_redirect(
			add_query_arg(
				[
					'action_performed' => 'promotion_deactivated',
					'handled_jobs'     => [ $post_id ],
				],
				remove_query_arg( [ 'action', 'post_id', 'nonce' ] )
			)
		);
		exit;
	}

	/**
	 * Add feedback notice after successful deactivation.
	 *
	 * @param array $actions_handled
	 *
	 * @return array
	 */
	public function add_action_notice( $actions_handled ) {

		$actions_handled['promotion_deactivated'] = [
			// translators: Placeholder (%s) is the name of the job listing affected.
			'notice' => __( 'Promotion for %s deactivated', 'wp-job-manager' ),
		];

		return $actions_handled;
	}

	/**
	 * Check if a job is promoted.
	 *
	 * @param int $post_id
	 *
	 * @return boolean
	 */
	public function is_promoted( $post_id ) {
		$promoted = get_post_meta( $post_id, '_promoted', true );

		return (bool) $promoted;
	}

	/**
	 * Deactivate promotion for a job.
	 *
	 * @param int $post_id
	 *
	 * @return boolean
	 */
	public function deactivate_promotion( $post_id ) {
		return update_post_meta( $post_id, '_promoted', 0 );
	}

	/**
	 * Handle display of new column
	 *
	 * @param string $column
	 */
	public function promoted_jobs_custom_columns( $column ) {
		global $post;

		if ( 'promoted_jobs' !== $column ) {
			return;
		}

		if ( $this->is_promoted( $post->ID ) ) {
			$nonce                  = wp_create_nonce( 'deactivate_promotion_' . $post->ID );
			$deactivate_action_link = add_query_arg(
				[
					'action'  => 'deactivate_promotion',
					'post_id' => $post->ID,
					'nonce'   => $nonce,
				]
			);
			echo '
			<span class="jm-promoted__status-promoted">' . esc_html__( 'Promoted', 'wp-job-manager' ) . '</span>
			<div class="row-actions">
				<a href="#" class="jm-promoted__edit">' . esc_html__( 'Edit', 'wp-job-manager' ) . '</a>
				|
				<a class="jm-promoted__deactivate delete" href="#" data-href="' . esc_url( $deactivate_action_link ) . '">' . esc_html__( 'Deactivate', 'wp-job-manager' ) . '</a>
			</div>
			';
		} else {
			$promote_url = add_query_arg(
				[
					'job_id' => $post->ID,
				],
				'https://wpjobmanager.com/promote-job/'
			);
			echo '<button class="promote_job button button-primary" data-href=' . esc_url( $promote_url ) . '>' . esc_html__( 'Promote', 'wp-job-manager' ) . '</button>';
		}
	}

	/**
	 * Store the promoted jobs template from wpjobmanager.com
	 *
	 * TODO: we need to fetch this from wpjobmanager.com and store in cache for X amount of time
	 * We should also have a fallback in case the API call fails.
	 * We need to have a fallback here because we can't use a `dialog` element inside the template
	 *
	 * @return string
	 */
	public function get_promote_jobs_template() {
		return '
		<style>
			.promote-job-modal {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
				padding: 30px 80px 50px 80px;
			}
			.promote-job-modal img.promote-jobs-image {
				width: 100%;
			}
			.promote-job-modal h2.promote-jobs-heading {
				font-size: 36px;
				font-weight: 300;
				line-height: 105%;
				margin-top: 0;
				width: 80%;
				margin-bottom: 0px;
			}
			.promote-job-modal .promote-job-modal-column-left {
				display: flex;
				justify-content: space-between;
				flex-direction: column;
			}
			.promote-list {
				margin: 0;
				padding: 0;
				list-style: none;
			}
			.promote-job-modal li.promote-list-item {
				background: url("data:image/svg+xml,<svg width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><mask id=\"mask0_20018663_2259\" style=\"mask-type:luminance\" maskUnits=\"userSpaceOnUse\" x=\"3\" y=\"5\" width=\"18\" height=\"14\"><path d=\"M8.75685 15.9L4.57746 11.7L3.18433 13.1L8.75685 18.7L20.698 6.69999L19.3049 5.29999L8.75685 15.9Z\" fill=\"white\"/></mask><g mask=\"url%28%23mask0_20018663_2259%29\"><rect width=\"23.8823\" height=\"24\" fill=\"%232270B1\"/></g></svg>") no-repeat 0 -3px;
				font-size: 14px;
				list-style: none;
				padding-left: 32px;
				margin: 12px 0;
			}
			.promote-job-modal .promote-job-modal-price {
				display: flex;
				flex-direction: column;
			}
			.promote-job-modal .promote-job-modal-price .price-text {
				font-size: 12px;
				text-transform: uppercase;
				color: #787c82;
			}
			.promote-job-modal .promote-job-modal-price span {
				margin-top: 10px;
				font-size: 36px;
				font-weight: 700;
			}
			.promote-job-modal .promote-buttons-group .button {
				padding: 10px 16px;
				border-radius: 2px;
				margin-right: 18px;
				border: 0;
			}
			.promote-job-modal .promote-buttons-group .button-primary {
				background: #2270b1;
				color: #fff;
			}
			.promote-job-modal .promote-buttons-group .button-secondary {
				background: #fff;
				color: #2270B1;
				border: 1px solid #2270B1;
			}
			@media screen and (max-width: 1000px) {
				.promote-job-modal-column-right {
					display: none;
				}
			}
			@media screen and (max-width: 782px) {
				.promote-job-modal {
					padding: 0px 25px 25px;
				}
			}
			.wpjm-dialog {
				border: 0;
				border-radius: 8px;
			}
			form.dialog {
				display: flex;
			}
			form.dialog button.dialog-close {
				margin: 10px 15px auto auto;
				content: "";
				background: none;
				border: 0;
				font-size: 0;
			}
			form.dialog button.dialog-close:after {
				content: "\2715";
				font-size: 20px;
			}
		</style>
		<div class="promote-job-modal">
			<div class="promote-job-modal-column-left">
				<h2 class="promote-jobs-heading">
					Promote your job on our partner network.
				</h2>

				<div class="promote-job-modal-price">
					<div class="price-text">Starting From</div>
					<span>$83.00</span>
				</div>

				<ul class="promote-list">
					<li class="promote-list-item">Your ad will get shared on our Partner Network</li>
					<li class="promote-list-item">Featured on jobs.blog for 7 days</li>
					<li class="promote-list-item">Featured on our weekly email blast</li>
				</ul>

				<slot name="buttons" class="promote-buttons-group">
					<button class="promote-button button button-primary" type="submit" href="#">Promote your job</button>
					<button class="promote-button button button-secondary" type="submit" href="#">Learn More</button>
				</slot>
			</div>
			<div class="promote-job-modal-column-right">
				<img class="promote-jobs-image" src="https://wpjobmanager.com/wp-content/uploads/2023/06/Right.jpg">
			</div>
		</div>';
	}

	/**
	 * Output the promote jobs template
	 *
	 * @return void
	 */
	public function promoted_jobs_admin_footer() {
		?>
		<template id="promote-job-template">
			<?php echo $this->get_promote_jobs_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</template>
		<dialog class="wpjm-dialog" id="promote-dialog"></dialog>

		<dialog class="wpjm-dialog deactivate-dialog" id="deactivate-dialog">
			<form class="dialog deactivate-button" method="dialog">
				<button class="dialog-close" type="submit">X</button>
			</form>
			<h2 class="deactivate-modal-heading">
				<?php esc_html_e( 'Are you sure you want to deactivate promotion for this job?', 'wp-job-manager' ); ?>
			</h2>
			<p>
				<?php esc_html_e( 'If you still have time until the promotion expires, this time will be lost and the promotion of the job will be canceled.', 'wp-job-manager' ); ?>
			</p>
			<form method="dialog">
				<div class="deactivate-action promote-buttons-group">
					<button class="dialog-close button button-secondary" type="submit">
						<?php esc_html_e( 'Cancel', 'wp-job-manager' ); ?>
					</button>
					<a class="deactivate-promotion button button-primary">
					<?php esc_html_e( 'Deactivate', 'wp-job-manager' ); ?>
					</a>
				</div>
			</form>
		</dialog>
		<?php
	}

}

WP_Job_Manager_Promoted_Jobs_Admin::instance();