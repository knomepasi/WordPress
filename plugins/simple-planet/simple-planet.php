<?php
/*
 *  Plugin Name: Simple Planet
 *  Description: Show posts from multiple feeds sorted by date via a widget.
 *  Author: Pasi Lallinaho
 *  Version: 1.1
 *  Author URI: https://open.knome.fi/
 *  Plugin URI: https://wordpress.knome.fi/
 *
 *  License: GNU General Public License v2 or later
 *  License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 */

/*  Plugin activation
 *
 */

register_activation_hook( __FILE__, 'SimplePlanetActivate' );

function SimplePlanetActivate( ) {
	add_option( 'simple_planet_items_default', 10 );
	add_option( 'simple_planet_refresh_default', 60 );
}

/*  Init plugin
 *
 */

add_action( 'plugins_loaded', 'SimplePlanetInit' );

function SimplePlanetInit( ) {
	/* Load text domain for i18n */
	load_plugin_textdomain( 'simple-planet', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/*  Widget class
 *
 */

add_action( 'widgets_init', function( ) { register_widget( 'SimplePlanetWidget' ); } );

class SimplePlanetWidget extends WP_Widget {
	/** constructor */
	function __construct( ) {
 		$widget_ops = array( 'description' => __( 'Show aggregated posts from multiple feeds sorted by date via a widget.', 'simple-planet' ) );
		$control_ops = array( 'width' => 500, 'height' => 400 );

		parent::__construct( 'simple-planet', _x( 'Simple Planet', 'widget name', 'simple-planet' ), $widget_ops, $control_ops );
	}

	/** @see WP_Widget::widget */
	function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );
		echo $before_widget;

		if( !empty( $title ) ) {
			echo $before_title . $title . $after_title;
		}

		if( !empty( $instance['description'] ) ) {
			echo wpautop( $instance['description'] );
		}

		$time_diff = $instance['refresh_in'] * 60;
		if( ( get_option( 'simple_planet_' . $args['widget_id'] . '_lastupdate', 0 ) + ( $time_diff * 60 ) ) < time( ) ) {
			# we need to update.
			$count = 0;
			$planet = array( );

			# include simplepie
			include_once( ABSPATH . WPINC . '/class-simplepie.php' );

			foreach( explode( "\n", $instance['feeds'] ) as $feed ) { 
				$feeds[] = rtrim( ltrim( $feed ) );
			}

			$feed = new SimplePie( );
			$feed->set_feed_url( $feeds );
			$feed->set_item_limit( $instance['items'] );
			$feed->init( );
			$feed->handle_content_type( );

			$items = $feed->get_items( 0, $instance['items'] );
	
			if( is_array( $items ) ) {
				# rss feed has items
				foreach( $items as $item ) {
					$item_id = $item->get_local_date( '%s' ) . "-" . $count;
					$planet[$item_id] = array(
						"title" => $item->get_title( ),
						"site" => $item->get_feed( )->get_title( ),
						"link" => $item->get_permalink( ),
						"author" => $item->get_author( )->get_name( )
					);

					$count++;
				}
			}

			if( is_array( $planet ) ) {
				krsort( $planet );
			}

			update_option( 'simple_planet_' . $args['widget_id'] . '_lastupdate', time( ) );
			update_option( 'simple_planet_' . $args['widget_id'] . '_posts', $planet );
		}

		# show posts
		$widget_posts = get_option( 'simple_planet_' . $args['widget_id'] . '_posts' );
		if( is_array( $widget_posts ) ) {
			?>
			<ul class="simple_planet">
				<?php foreach( $widget_posts as $post ) { ?>
					<li>
						<a href="<?php echo $post['link']; ?>">
							<span class="title"><?php echo $post['title']; ?></span>
							<?php if( isset( $post['site'] ) && $post['author'] != $post['site'] ) { ?>
								<span class="author"><strong><?php echo $post['site']; ?></strong>, <?php echo $post['author']; ?></span>
							<?php } else { ?>
								<span class="author"><?php echo $post['author']; ?></span>
							<?php } ?>
						</a>
					</li>
				<?php } ?>
			</ul>
			<?php
		}

		echo $after_widget;
	}

	/** @see WP_Widget::update */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['description'] = wp_kses( $new_instance['description'], array( 'a' => array( 'href' => array( ) ) ) );
		$instance['feeds'] = sanitize_textarea_field( $new_instance['feeds'] );
		if( is_numeric( $new_instance['items'] ) ) {
			$instance['items'] = intval( $new_instance['items'] );
		}
		if( is_numeric( $new_instance['refresh_in'] ) ) {
			$instance['refresh_in'] = intval( $new_instance['refresh_in'] );
		}

		return $instance;
	}

	/** @see WP_Widget::form */
	function form( $instance ) {
		$title = esc_attr( $instance['title'] );
		$description = esc_attr( $instance['description'] );
		$feeds = esc_attr( $instance['feeds'] );
		$items = esc_attr( $instance['items'] );
		$refresh_in = esc_attr( $instance['refresh_in'] );

		if( empty( $items ) ) { $items = get_option( 'simple_planet_items_default' ); }
		if( empty( $refresh_in ) ) { $refresh_in = get_option( 'simple_planet_refresh_default' ); }
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title', 'simple-planet' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />

			<label for="<?php echo $this->get_field_id( 'description' ); ?>"><?php _e( 'Description', 'simple-planet' ); ?></label>
			<textarea class="widefat" id="<?php echo $this->get_field_id( 'description' ); ?>" name="<?php echo $this->get_field_name( 'description' ); ?>"><?php echo $description; ?></textarea>

			<label for="<?php echo $this->get_field_id( 'feeds' ); ?>"><?php _e( 'Feeds (one per line)', 'simple-planet' ); ?></label>
			<textarea class="widefat" id="<?php echo $this->get_field_id( 'feeds' ); ?>" name="<?php echo $this->get_field_name( 'feeds' ); ?>"><?php echo $feeds; ?></textarea>

			<label for="<?php echo $this->get_field_id( 'items' ); ?>"><?php _e( 'Items to show', 'simple-planet' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'items' ); ?>" name="<?php echo $this->get_field_name( 'items' ); ?>" type="text" value="<?php echo $items; ?>" />

			<label for="<?php echo $this->get_field_id( 'refresh_in' ); ?>"><?php _e( 'Minimum refresh interval (in minutes)', 'simple-planet' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'refresh_in' ); ?>" name="<?php echo $this->get_field_name( 'refresh_in' ); ?>" type="text" value="<?php echo $refresh_in; ?>" />
		</p>
		<?php 
	}

}

?>
