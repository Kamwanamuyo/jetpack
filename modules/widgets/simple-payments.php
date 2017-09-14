<?php
/*
Plugin Name: Simple Payments
Description: Simple Payments button implemented as a widget.
Version: 1.0
Author: Automattic Inc.
Author URI: http://automattic.com/
License: GPLv2 or later
*/

function jetpack_register_widget_simple_payments() {
	register_widget( 'Simple_Payments_Widget' );
}
add_action( 'widgets_init', 'jetpack_register_widget_simple_payments' );

class Simple_Payments_Widget extends WP_Widget {
	private static $dir       = null;
	private static $url       = null;
	private static $labels    = null;
	private static $defaults  = null;
	private static $config_js = null;

	function __construct() {
		$widget = array(
			'classname'   => 'simple-payments',
			'description' => __( 'Add a simple payment button.', 'jetpack' ),
		);

		parent::__construct(
			'Simple_Payments_Widget',
			/** This filter is documented in modules/widgets/facebook-likebox.php */
			apply_filters( 'jetpack_widget_name', __( 'Simple Payments', 'jetpack' ) ),
			$widget
		);

		self::$dir = trailingslashit( dirname( __FILE__ ) );
		self::$url = plugin_dir_url( __FILE__ );
		// add form labels for translation
		/*
		self::$labels = array(
			'year'    => __( 'year', 'jetpack' ),
		);
		*/

		// add_action( 'wp_enqueue_scripts', array( __class__, 'enqueue_template' ) );
		// add_action( 'admin_enqueue_scripts', array( __class__, 'enqueue_admin' ) );
	}

	public static function enqueue_admin( $hook_suffix ) {
		if ( 'widgets.php' == $hook_suffix ) {
			// wp_enqueue_style( 'milestone-admin', self::$url . 'style-admin.css', array(), '20161215' );
		}
	}

	public static function enqueue_template() {
		// wp_enqueue_script( 'milestone', self::$url . 'milestone.js', array( 'jquery' ), '20160520', true );
	}

	protected function get_product_args( $product_id ) {
		$product = $product_id ? get_post( $product_id ) : null;
		$product_args = array();
		if ( $product && ! is_wp_error( $product ) && $product->post_type === Jetpack_Simple_Payments::$post_type_product ) {
			$product_args = array(
				'name' => get_the_title( $product ),
				'description' => $product->post_content,
				'currency' => get_post_meta( $product->ID, 'spay_currency', true ),
				'price' => get_post_meta( $product->ID, 'spay_price', true ),
				'multiple' => get_post_meta( $product->ID, 'spay_multiple', true ),
				'email' => get_post_meta( $product->ID, 'spay_email', true ),
			);
		} else {
			$product_id = null;
		}

		$current_user = wp_get_current_user();
		return wp_parse_args( $product_args, array(
			'name' => '',
			'description' => '',
			'currency' => 'USD', // TODO: Geo-localize?
			'price' => 1000,
			'multiple' => '0',
			'email' => $current_user->user_email,
		) );
	}

    /**
     * Widget
     */
    function widget( $args, $instance ) {
		$instance = wp_parse_args( $instance, array(
			'title' => '',
			'product_id' => null,
		) );

		$product_args = $this->get_product_args( $instance['product_id'] );

		echo $args['before_widget'];

		$title = apply_filters( 'widget_title', $instance['title'] );
		if ( ! empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		echo '<div class="simple-payments-content">';

		$attrs = array( 'id' => $instance['product_id'] );

		$JSP = Jetpack_Simple_Payments::getInstance();
		// display the product on the front end here
		echo $JSP->parse_shortcode( $attrs );

		echo '</div><!--simple-payments-->';

		echo $args['after_widget'];

	    /** This action is documented in modules/widgets/gravatar-profile.php */
	    do_action( 'jetpack_stats_extra', 'widget_view', 'simple-payments' );
    }

    /**
     * Update
     */
    function update( $new_instance, $old_instance ) {
		$product_id = isset( $old_instance['product_id'] ) ? $old_instance['product_id'] : null;
		$product = $product_id ? get_post( $product_id ) : 0;
		if ( ! $product || is_wp_error( $product ) || $product->post_type !== Jetpack_Simple_Payments::$post_type_product ) {
			$product_id = 0;
		}
		return array(
			'title' => $new_instance['title'],
			'product_id' => wp_insert_post( array(
				'ID' => $product_id,
				'post_type' => Jetpack_Simple_Payments::$post_type_product,
				'post_status' => 'publish',
				'post_title' => $new_instance['name'],
				'post_content' => $new_instance['description'],
				'meta_input' => array(
					'spay_currency' => $new_instance['currency'],
					'spay_price' => $new_instance['price'],
					'spay_multiple' => $new_instance['multiple'],
					'spay_email' => $new_instance['email'],
				),
			) ),
		);
    }

    /**
     * Form
     */
    function form( $instance ) {
		$instance = wp_parse_args( $instance, array(
			'title' => '',
			'product_id' => null,
		) );

		$product_args = $this->get_product_args( $instance['product_id'] );
        ?>

	<div class="simple-payments">
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title', 'jetpack' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'name' ); ?>"><?php _e( 'What are you selling?', 'jetpack' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'name' ); ?>" name="<?php echo $this->get_field_name( 'name' ); ?>" type="text" placeholder="<?php echo esc_attr_e( 'Product name', 'jetpack' ); ?>" value="<?php echo esc_attr( $product_args['name'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'description' ); ?>"><?php _e( 'Description', 'jetpack' ); ?></label>
			<textarea class="widefat" rows=5 id="<?php echo $this->get_field_id( 'description' ); ?>" name="<?php echo $this->get_field_name( 'description' ); ?>"><?php echo esc_html( $product_args['description'] ); ?></textarea>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'currency' ); ?>"><?php _e( 'Currency', 'jetpack' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'currency' ); ?>" name="<?php echo $this->get_field_name( 'currency' ); ?>" type="text" value="<?php echo esc_attr( $product_args['currency'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'price' ); ?>"><?php _e( 'Price', 'jetpack' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'price' ); ?>" name="<?php echo $this->get_field_name( 'price' ); ?>" type="text" value="<?php echo esc_attr( $product_args['price'] ); ?>" />
		</p>
		<p>
			<input id="<?php echo $this->get_field_id( 'multiple' ); ?>" name="<?php echo $this->get_field_name( 'multiple' ); ?>" type="checkbox" <?php if ( '1' === $product_args['multiple'] ) { ?>checked="checked"<?php } ?> />
			<label for="<?php echo $this->get_field_id( 'multiple' ); ?>"><?php _e( 'Allow people to buy more than one item at a time.', 'jetpack' ); ?></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'email' ); ?>"><?php _e( 'Email', 'jetpack' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'email' ); ?>" name="<?php echo $this->get_field_name( 'email' ); ?>" type="email" value="<?php echo esc_attr( $product_args['email'] ); ?>" />
			This is where PayPal will send your money. To claim a payment, you'll need a PayPal account connected to a bank account.
		</p>
	</div>

		<?php
    }
}