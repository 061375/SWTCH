<?php
// Run through wp eval-file from the WordPress root.
$urls = swtch_get_export_urls();
$query = new WP_Query( [ 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => (int) get_option( 'posts_per_page' ), 'ignore_sticky_posts' => true ] );
if ( $query->max_num_pages < 2 ) { throw new RuntimeException( 'Fixture needs multiple pages.' ); }
for ( $page = 2; $page <= $query->max_num_pages; $page++ ) {
    $url = home_url( '/page/' . $page . '/' );
    if ( ! in_array( $url, $urls, true ) ) { throw new RuntimeException( 'Missing URL: ' . $url ); }
    $result = swtch_generate_static_page( $url );
    if ( ! $result['success'] ) { throw new RuntimeException( $result['message'] ); }
    $html = file_get_contents( swtch_get_export_path( $url ) );
    if ( ! str_contains( $html, 'page-numbers current' ) && ! str_contains( $html, 'aria-current="page"' ) ) {
        throw new RuntimeException( 'Missing pagination in rendered page: ' . $url );
    }
    echo 'PASS: exported ' . $url . "\n";
}
// Exercise a separate posts page and a static front page with no posts page
// without changing stored WordPress settings.
$page_ids = get_posts( [ 'post_type' => 'page', 'numberposts' => 1, 'fields' => 'ids' ] );
if ( ! $page_ids ) { throw new RuntimeException( 'Fixture needs a page.' ); }
$static_front = static function () { return 'page'; };
$posts_page = static function () use ( $page_ids ) { return $page_ids[0]; };
add_filter( 'pre_option_show_on_front', $static_front );
add_filter( 'pre_option_page_for_posts', $posts_page );
try {
    $separate_urls = swtch_get_export_urls();
    $expected = trailingslashit( get_permalink( $page_ids[0] ) ) . 'page/2/';
    if ( ! in_array( $expected, $separate_urls, true ) ) { throw new RuntimeException( 'Separate posts page pagination missing.' ); }
    echo "PASS: separate posts page pagination\n";
    remove_filter( 'pre_option_page_for_posts', $posts_page );
    $no_posts_page = static function () { return '0'; };
    add_filter( 'pre_option_page_for_posts', $no_posts_page );
    if ( in_array( home_url( '/page/2/' ), swtch_get_export_urls(), true ) ) { throw new RuntimeException( 'Static front page incorrectly paginated.' ); }
    remove_filter( 'pre_option_page_for_posts', $no_posts_page );
    echo "PASS: static front page without posts index\n";
} finally {
    remove_filter( 'pre_option_show_on_front', $static_front );
    remove_filter( 'pre_option_page_for_posts', $posts_page );
}

