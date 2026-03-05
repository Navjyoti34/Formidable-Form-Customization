<?php 

global $redux_builder_amp;  


$post_word_count = get_post_word_count(amp_current_post_id());
$min_to_read = floor($post_word_count/300);
$sec_to_read = ceil($post_word_count%300/300*60);

function amp_current_host() {
    $is_ssl = is_ssl();
    $protocol = $is_ssl ? 'https://' : 'http://';
    $current_host = $protocol . parse_url(get_permalink(), PHP_URL_HOST);

    return $current_host;
}

function amp_current_post_id() {
  return get_the_ID();
}

if($sec_to_read < 10) {
  $sec_to_read = sprintf("%02d", $sec_to_read);
}

if($sec_to_read >= 30) {
  $min_to_read += 1;
}

$readTime = "{$min_to_read} minute read";

?>
<!doctype html>
<html amp>
<head>
  <meta charset="utf-8">
    <link rel="dns-prefetch" href="https://cdn.ampproject.org">

  <?php do_action( 'amp_post_template_head', $this ); ?>
  <style amp-custom>
  <?php $this->load_parts( array( 'style' ) ); ?>
  <?php do_action( 'amp_post_template_css', $this ); ?>
  </style>
      
</head>
<body class="single-post <?php
  if( is_page() )   echo 'amp-single-page';
  else              echo 'amp-single';
?>">

<?php include AMPFORWP_CUSTOM_THEME . '/template/header-bar.php'; do_action( 'ampforwp_after_header', $this ); ?>
<div id="article">
  <section class="bg-dark header-article d-flex align-items-center position-relative" style="background: url('<?php echo get_the_post_thumbnail_url(amp_current_post_id(), 'full'); ?>')">

  <?php

    $thumbnail_image = get_posts(array('p' => get_post_thumbnail_id(amp_current_post_id()), 'post_type' => 'attachment'));
    $thumbnail_caption = $thumbnail_image[0]->post_excerpt;

    if ($thumbnail_image && isset($thumbnail_image[0]) && !empty($thumbnail_caption)) {
     echo '<div class="caption px-5"><small>' . $thumbnail_caption . '</small></div>';
    }

    $showComments = get_comments_number(amp_current_post_id());

    if($showComments == 0) {
      $showComments = false;
    }

    $article_author = get_the_author_meta( 'display_name' , get_post_field ('post_author', amp_current_post_id()));
  ?>
  </section>
  <section class="py-10 pys-xl-15 px-3">
        <div class="container">
          <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">

              <div class="header-title my-5">

                <div class="mobile-only d-lg-none">
                  <ul class="fa-ul">
                    <?php 
                      $post_tags = get_the_tags(amp_current_post_id());
                    ?>
                    <li><span class="fa-li"><i class="fa-solid fa-hashtag"></i></span><a href="<?php echo append_amp_param_omeda_optics(get_category_link(get_the_category(amp_current_post_id())[0])); ?>" class="fw-900 text-uppercase"><?php echo get_the_category(amp_current_post_id())[0]->name; ?></a></li>
                    <?php if($post_tags) { ?>
                    <li><span class="fa-li"><i class="fa-solid fa-tags"></i></span><?php foreach($post_tags as $tag) { echo '<a href="' . append_amp_param_omeda_optics(get_tag_link($tag->term_id)) . '">' . ucfirst($tag->name) . '</a>'; if( next( $post_tags )) { echo ', '; } } ?></li>
                    <?php } ?>
                    <?php if($showComments) { ?>
                    <li><span class="fa-li"><i class="fa-solid fa-comment"></i></span><a href=<?php echo append_amp_param_omeda_optics("?j=comments"); ?>><?php echo $showComments; ?> comments</a></li>
                    <?php } ?>
                    <li><span class="fa-li"><i class="fa-solid fa-clock"></i></span><?php echo $readTime; ?></li>
                  </ul>
                </div>

                <div class="desktop-only d-none d-lg-block py-2" >
                  <ul class="nav">
                    <li class="nav-item">
                      <span class="ps-0 text-uppercase fw-900"><i class="fa-solid fa-hashtag"></i> <a href="<?php echo append_amp_param_omeda_optics(get_category_link(get_the_category(amp_current_post_id())[0])); ?>" class="fw-900 text-uppercase"><?php echo get_the_category(amp_current_post_id())[0]->name; ?></a></span>
                    </li>
                    <?php if($post_tags) { ?>
                    <li class="nav-item">
                      <span class="ps-0"><i class="fa-solid fa-tags"></i> <?php foreach($post_tags as $tag) { echo '<a href="' . append_amp_param_omeda_optics(get_tag_link($tag->term_id)) . '">' . ucfirst($tag->name) . '</a>'; if( next( $post_tags )) { echo ', '; } } ?></span>
                    </li>
                    <?php } ?>
                     <?php if($showComments) { ?>
                    <li class="nav-item">
                      <span class="ps-0"><i class="fa-solid fa-comment"></i> <a href=<?php echo append_amp_param_omeda_optics("?j=comments"); ?>><?php echo $showComments; ?> comments</a></span>
                    </li>
                    <?php } ?>
                    <li class="nav-item">
                      <span class="ps-0"><i class="fa-solid fa-clock"></i> <?php echo $readTime; ?></span>
                    </li>
                  </ul>
                </div>

                <h2 class="fs-1"><?php echo get_the_title(amp_current_post_id()); ?></h2>

               <div class="d-flex align-items-center mb-2">
                  <img src="<?php echo get_avatar_url( get_the_author_meta( 'ID' ), array( 'size' => 96 ) ); ?>" class="img-fluid rounded-circle" width="36" height="36" alt="<?php echo $article_author; ?>">
                  <div class="ps-3">
                    <p class="mb-1">By <span class="fw-bold"> <a href="<?php echo append_amp_param_omeda_optics(get_author_posts_url(get_the_author_meta('ID'))); ?>"><?php echo $article_author; ?></a></span></p>
                  </div>
                </div>

              </div>

              <div class="content">

              <?php

function appendHTML(DOMNode $parent, $source) {
    $tmpDoc = new DOMDocument();
    libxml_use_internal_errors(true); // Enable error handling
    $tmpDoc->loadHTML($source);
    libxml_use_internal_errors(false); // Disable error handling after loading
    foreach ($tmpDoc->getElementsByTagName('body')->item(0)->childNodes as $node) {
        $node = $parent->ownerDocument->importNode($node, true);
        $parent->appendChild($node);
    }
}


function append_amp_param_omeda_optics($url) {
  if (!strpos($url, '?') && !filter_var($url, FILTER_VALIDATE_URL)) {
      $is_ssl = is_ssl();
      $protocol = $is_ssl ? 'https://' : 'http://';
      $incoming_params = $url;
      $current_url = amp_current_host() . $_SERVER['REQUEST_URI'];
      $current_url = str_replace(['www.', 'amp'], '', $current_url);
      $url = esc_url($current_url . '/' . $incoming_params);
  }

  $categories = get_the_category(amp_current_post_id());
  $tags = get_the_tags(amp_current_post_id());

  $gathered_categories = array();
  $gathered_tags = array();

  if (!empty($categories)) {
      foreach ($categories as $category) {
          $gathered_categories[] = $category->name;
      }
  }

  if (!empty($tags)) {
      foreach ($tags as $tag) {
        $gathered_tags[] = $tag->name;
      }
  }

  $new_url = $url;
  $delimiter = ',';
  $new_param = 'ampcategory=' . urlencode(implode($delimiter, $gathered_categories)) . '&amptag=' . urlencode(implode($delimiter, $gathered_tags));


  if (strpos($new_url, '?') !== false) {
      $new_url .= '&' . $new_param;
  } else {
      $new_url .= '?' . $new_param;
  }

  return $new_url;
}


do_action('ampforwp_inside_post_content_before'); 
                  $amp_custom_content_enable = get_post_meta( $this->get( 'post_id' ) , 'ampforwp_custom_content_editor_checkbox', true);

                  $post_content = null;
                  // Normal Front Page Content
                  if ( !$amp_custom_content_enable ) {
                    $post_content = $this->get( 'post_amp_content' ); // amphtml content; no kses
                  } else {
                    // Custom/Alternative AMP content added through post meta  
                    $post_content = $this->get( 'ampforwp_amp_content' );
                  } 
                  
                  $dom = new DOMDocument();
                  @$dom->loadHTML(mb_convert_encoding($post_content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                  $dom->encoding = 'utf-8';

                  $items = $dom->getElementsByTagName('p');
                  $links = $dom->getElementsByTagName('a');

                  $host = parse_url(home_url(), PHP_URL_HOST);

                  foreach ($links as $link) {
                      $url = $link->getAttribute('href');

                      $link_host = str_replace('www.', '', parse_url($url, PHP_URL_HOST));

                      if($link_host === $host) {
                        $link->setAttribute('href', append_amp_param_omeda_optics($url));
                      }
                  }

                  $p_elements = $items;
                  $count = 0;

                  // parse this elements, if available
                  if (!is_null($p_elements)) {
                      for ($i = 0; $i < $p_elements->length; $i++) {

                          $p_element = $p_elements->item($i);
                          $p_content = $p_element->textContent;

                          $word_count = str_word_count($p_content);
                          $char_count = strlen($p_content);

                          if($word_count >= 15 && $char_count >= 250) {
                            if($count >= 3) {
                                $parentNode = $p_elements->item($i)->parentNode;

                                if ($parentNode instanceof DOMElement) {
                                  $classes = $parentNode->getAttribute('class');
                                  $ids = $parentNode->getAttribute('id');

                                  if (str_contains($classes, 'wp-block-group') || str_contains($ids, 'content-markup')) {
                                    $span_element = $dom->createElement('div');
                                    appendHTML($span_element, '<br/><br/><center><amp-ad width="300" height="250" type="doubleclick" data-slot="/184645914/QLTamp300x250"></amp-ad></center><br/><br/>');
                                    $span_element->setAttribute('class', 'ad-injection');

                                    $p_element->appendChild($span_element);
                                  }
                                  
                                  $count = 0;
                                }
                            }

                            

                            $count += 1;
                          }
                      }
                  }

                  $html = $dom->saveHTML();
                  $html = html_entity_decode($html, ENT_COMPAT, 'UTF-8');

                  echo $html;

                  echo "Like what you're reading? <a href='" . append_amp_param_omeda_optics((amp_current_host() . "/newsletter")) . "'>signup for our newsletter</a>.";

                  do_action('ampforwp_inside_post_content_after') ?>
              </div>
              <br>
              <br>
            </div>
          </div>
        </div>
      </section>
</div>
<?php $this->load_parts( array( 'footer' ) ); ?>
<?php do_action( 'amp_post_template_footer', $this ); ?>
</body>
</html>