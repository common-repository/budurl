<?php
// Creating the widget 
class budurl_widget extends WP_Widget {

    function __construct() {
        parent::__construct(
            // Base ID of your widget
            'budurl_widget', 

            // Widget name will appear in UI
            __('Recent BudURL', 'budurl_widget_domain'), 

            // Widget description
            array( 'description' => __( 'Recent Link BudURL', 'budurl_widget_domain' ), ) 
        );
    }

    // Creating widget front-end
    // This is where the action happens
    public function widget( $args, $instance ) {
        $title = apply_filters( 'widget_title', $instance['title'] );
        $limit = (int) $instance['limit'];
        // before and after widget arguments are defined by themes
        echo $args['before_widget'];
        if ( ! empty( $title ) )
            echo $args['before_title'] . $title . $args['after_title'];

        // This is where you run the code and display the output
        // echo __( 'Hello, World!', 'budurl_widget_domain' );
        $query = array(
            'post_type'           => array('post', 'page'),
            'offset'              => 0,
            'posts_per_page'      => $limit,
            'orderby'             => "modified",
            'order'               => "DESC",
        );
        $posts = new WP_Query( $query );
        $html = '';
        if ( $posts->have_posts() )  {
            // var_dump($posts);
            while ( $posts->have_posts() ) : $posts->the_post();
                $html .= '<ul>';
                $html .= '<li><a href="'.esc_url( get_permalink() ).'">'.esc_attr( get_the_title() ).'</a></li>';
                $html .= '</ul>';
            endwhile;
        }
        echo $html;
        wp_reset_postdata();
        echo $args['after_widget'];
    }

    // Widget Backend 
    public function form( $instance ) {
        if ( isset( $instance[ 'title' ] ) ) {
            $title = $instance[ 'title' ];
        }
        else {
            $title = __( 'New title', 'budurl_widget_domain' );
        }
        if ( isset( $instance[ 'limit' ] ) ) {
            $limit = (int) ($instance[ 'limit' ]);
        }
        else {
            $limit = __( '6', 'budurl_widget_domain' );
        }
        // Widget admin form
        ?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
            <label for="<?php echo $this->get_field_id( 'limit' ); ?>"><?php _e( 'Number of Links:' ); ?></label> 
            <input class="widefat" id="<?php echo $this->get_field_id( 'limit' ); ?>" name="<?php echo $this->get_field_name( 'limit' ); ?>" type="number" step="1" min="-1" value="<?php echo (int)( $limit ); ?>" />
        </p>
        <?php 
    }
    
    // Updating widget replacing old instances with new
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
        $instance['limit']            = (int)( $new_instance['limit'] );
        return $instance;
    }
} // Class budurl_widget ends here

