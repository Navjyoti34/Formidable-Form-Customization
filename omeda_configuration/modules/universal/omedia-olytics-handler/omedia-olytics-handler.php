<?php

function capture_slug_list($post_id) {
    $cat_slugs = array();

    if (is_singular('post')) {
        $cats =  get_the_category();
    } else if (is_product_category() || is_product()) {
        global $product;
        $post_categories = get_the_terms($post_id, 'product_cat');
        if (!empty($post_categories) && !is_wp_error($post_categories)) {
            $cats = array_filter(get_the_terms($post_id, 'product_cat', array('order' => 'DESC', 'hide_empty' => true)));
        }
    } else {
        $cats = array( get_category( get_query_var( 'cat' ) ) );
    }

    if(!empty($cats)) {
        foreach($cats as $cat) {
            if(property_exists($cat, 'slug')) {
                $cat_slugs[] = $cat->slug;
            }
        }
    }
    
    return implode(',', $cat_slugs);
}

function implement_omeda_assets() {
    return <<<EOD
<link rel="stylesheet" href="https://olytics.omeda.com/olytics/css/v3/p/olytics.css" />
<script>
window.olytics||(window.olytics=[]),window.olytics.methods=["fire","confirm"],window.olytics.factory=function(i){return function(){var t=Array.prototype.slice.call(arguments);return t.unshift(i),window.olytics.push(t),window.olytics}};for(var i=0;i<window.olytics.methods.length;i++){var method=window.olytics.methods[i];window.olytics[method]=window.olytics.factory(method)}olytics.load=function(i){if(!document.getElementById("olyticsImport")){window.a=window.olytics;var t=document.createElement("script");t.async=!0,t.id="olyticsImport",t.type="text/javascript";var o="";void 0!==i&&void 0!==i.oid&&(o=i.oid),t.setAttribute("data-oid",o),t.src="https://olytics.omeda.com/olytics/js/v3/p/olytics.min.js",t.addEventListener("load",function(t){for(olytics.initialize({Olytics:i});window.a.length>0;){var o=window.a.shift(),s=o.shift();olytics[s]&&olytics[s].apply(olytics,o)}},!1);var s=document.getElementsByTagName("script")[0];s.parentNode.insertBefore(t,s)}},olytics.load({oid:"1786cbc6a7c24809a2f0cfad4c97f592"});
</script>
EOD;
}

add_action( 'wp_logout', function() { $expire = time() + 3600; setcookie('wp_user_logged_out', 1, $expire, '/'); $_COOKIE['wp_user_logged_out'] = 1; wp_redirect(home_url() . '?iq=' . time()); exit(); }, 10, 0);
add_action( 'set_auth_cookie', function() { setcookie('wp_user_logged_in', 1, $expire, '/'); $_COOKIE['wp_user_logged_in'] = 1; }, 10, 0);

function olytics_lession_video_play_fire($olytics_dump, $post_title) {
return <<<EOD
document.addEventListener('DOMContentLoaded', function() {
    var iframes = document.querySelectorAll('.ld-video > iframe');

    if (iframes.length > 0) {
        var olyticsLoad = $olytics_dump;

        iframes.forEach(function(iframe) {
            var player = new Vimeo.Player(iframe);

            player.on('play', function() {
                olyticsLoad['VidPlay'] = `$post_title`;
                olyticsLoad['Action'] = 'Video Start';

                olytics.fire(olyticsLoad);
            });

            player.on('ended', function() {
                olyticsLoad['VidPlay'] = `$post_title`;
                olyticsLoad['Action'] = 'Video completed';

                olytics.fire(olyticsLoad);
            });
        });
    }
});
EOD;
}

function olytics_instant_download_fire($olytics_dump, $post_title) {
return <<<EOD
document.addEventListener('DOMContentLoaded', function() {
    var downloadButton = document.querySelector('.download_to_device_button');

    downloadButton.addEventListener('click', function() {
        var olyticsLoad = $olytics_dump;

        olyticsLoad['PDF'] = `$post_title`;

        olytics.fire(olyticsLoad);
    });
});
EOD;
}

function olytics_pdf_fire($olytics_dump) {
return <<<EOD
document.addEventListener('DOMContentLoaded', function() {
    var targetElement = document.getElementById('pdf-ready-text');

    if (targetElement) {
        var callback = function(mutationsList, observer) {
            for(var mutation of mutationsList) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    if (!targetElement.classList.contains('d-none')) {
                        if (typeof window.productTitle === 'undefined') {
                            return;
                        }

                        var olyticsLoad = $olytics_dump;

                        if (typeof window.productCategories !== 'undefined') {
                            olyticsLoad['category'] = window.productCategories;
                        }

                        olyticsLoad['PDF'] = window.productTitle;
                        olytics.fire(olyticsLoad);
                    }
                }
            }
        };

        var observer = new MutationObserver(callback);

        var config = { attributes: true, attributeFilter: ['class'] };

        observer.observe(targetElement, config);
    }
});
EOD;
}

function olytics_course_review_fire($olytics_dump, $post_title) {
return <<<EOD
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.rating') && document.querySelector('textarea[name="reviewText"]')) {
        document.querySelector('input[type="submit"][value="Submit Review"]').addEventListener('click', function(event) {
            event.preventDefault();

            var form = this.closest('form');

            var allFilled = true;

            var ratingChecked = form.querySelector('input[name="rating"]:checked');

            if (!ratingChecked) {
                allFilled = false;
                Swal.fire({
                  icon: 'error',
                  title: 'Validation Error',
                  text: `Please make sure to set a rating.`,
                });
                return;
            }

            form.querySelectorAll('input:not([name="rating"]), textarea, select').forEach(function(element) {
                if (!element.value.trim()) {
                    allFilled = false;
                    element.classList.add('error');
                } else {
                    element.classList.remove('error');
                }
            });

            if (allFilled) {
                var olyticsLoad = $olytics_dump;
                olyticsLoad['RatingReview'] = `${post_title}`;
                olyticsLoad['Action'] = 'Review left';
                olytics.fire(olyticsLoad);
                form.submit();
            } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Validation Error',
                  text: `Please make sure to complete the review form.`,
                });
                return;
            }
        });
    }
});
EOD;
}

function olytics_reviews_fire($olytics_dump, $post_title) {
return <<<EOD
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('commentform');

    form.addEventListener('submit', function(event) {
      const inputs = form.querySelectorAll('input, textarea, select');
      let allValid = true;

      inputs.forEach(input => {
        if (input.hasAttribute('required')) {
          let isValid = false;

          if (input.tagName.toLowerCase() === 'input' || input.tagName.toLowerCase() === 'textarea') {
            isValid = input.value.trim() !== '';
          }

          if (input.tagName.toLowerCase() === 'select') {
            isValid = input.value !== '';
          }

          const label = form.querySelector(`label[for="\${input.id}"]`);

          if (label && !isValid) {
            event.preventDefault();
            Swal.fire({
              icon: 'error',
              title: 'Validation Error',
              text: `Please make sure \${input.id} is filled in.`,
            });
            allValid = false;
            return;
          }
        }
      });

      if (allValid) {
        var olyticsLoad = $olytics_dump;
        olyticsLoad['RatingReview'] = `${post_title}`;
        olyticsLoad['Action'] = 'Review left';
        olytics.fire(olyticsLoad);
      } else {
        event.preventDefault();
      }
    });
});
EOD;
}

function olytics_lession_track_progress($olytics_dump) {
return <<<EOD
document.addEventListener('DOMContentLoaded', function() {
    var progressBar = document.querySelector('.ld-progress-bar-percentage');

    if (progressBar) {
        var percentage = parseFloat(progressBar.style.width.replace('%', '')).toString();
        var olyticsLoad = $olytics_dump;
        var courseEntryTitle = document.querySelector('.course-entry-title');

        if (courseEntryTitle) {
            var textContent = courseEntryTitle.textContent.trim();
            olyticsLoad['WorkShop'] = (percentage).toString();
            olytics.fire(olyticsLoad);
        }
    }
});
EOD;
}

function olytics_comment_fire($olytics_dump, $post_title) {
return <<<EOD
document.addEventListener('DOMContentLoaded', function() {
    var commentSubmitButton = document.querySelector('.comment-respond #commentform input[name="submit"]');

    if (commentSubmitButton) {
        commentSubmitButton.addEventListener('click', function(event) {
            var olyticsLoad = $olytics_dump;
            olyticsLoad['RatingReview'] = `${post_title}`;
            olyticsLoad['Action'] = 'Comment left';
            olytics.fire(olyticsLoad);
        });
    }
});
EOD;
}

function olytics_ebook_fire($olytics_dump){
    return <<<EOD
    document.addEventListener('DOMContentLoaded', function() {
        var gpmEbookProducts = document.querySelectorAll('li.gpm-ebook-product a.woocommerce-loop-product__link');

    gpmEbookProducts.forEach(function(gpmEbookProduct) {
        gpmEbookProduct.addEventListener('click', function(event) {
            console.log(olyticsLoad);
            var post_title = this.getAttribute('data-title');
            var olyticsLoad = $olytics_dump;
            olyticsLoad['PDF'] = post_title;
            olyticsLoad['Action'] = 'Ebook Download';                
            olytics.fire(olyticsLoad);
        });
    });
    });
    EOD;
}

function olytics_digital_mag_fire($olytics_dump){
    return <<<EOD
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector(".site-content-grid").addEventListener("click", function(event) {
        let targetRow = event.target.closest("li.gpm-digital-product");
        if (targetRow) {
        let productLink = targetRow.querySelector("a.woocommerce-loop-product__link");
        if (productLink) {
            var post_title= productLink.getAttribute('data-title');
            var olyticsLoad = $olytics_dump;
            olyticsLoad['PDF'] =post_title;
            olyticsLoad['Action'] = 'Digital Edition download';
            olytics.fire(olyticsLoad);
        }    
        }    
    });
    });
    EOD;
}

function olytics_zip_fire($olytics_dump){
    return <<<EOD
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector(".woocommerce-table--order-downloads").addEventListener("click", function(event) {
        let targetRow = event.target.closest(".download-file");
        if (targetRow) {
        let productLink = targetRow.querySelector("a[data-action]");
        console.log(productLink);
        if (productLink) {
        event.preventDefault();
        let loader = document.createElement("div");
                loader.id = "custom-loader";
                loader.innerHTML = "Loading...";
                loader.style.position = "fixed";
                loader.style.top = "50%";
                loader.style.left = "50%";
                loader.style.transform = "translate(-50%, -50%)";
                loader.style.padding = "10px 20px";
                loader.style.background = "rgba(0, 0, 0, 0.8)";
                loader.style.color = "#fff";
                loader.style.borderRadius = "5px";
                loader.style.zIndex = "9999";
                document.body.appendChild(loader);
            var post_title= productLink.getAttribute('data-title');
            var action= productLink.getAttribute('data-action');
            var olyticsLoad = $olytics_dump;
            olyticsLoad['PDF'] =post_title;
            olyticsLoad['Action'] = action;
            olytics.fire(olyticsLoad);
            setTimeout(() => {
            document.body.removeChild(loader);
                    window.location.href = productLink.href;
                }, 1500);
        }    
        }    
    });
    });
    EOD;
}

function olytics_metered_fire($olytics_dump, $post_title) {
return <<<EOD
(function() {
    let articleCountValue;
    let articleMaxedValue;

    Object.defineProperty(window, 'articleCount', {
        get() {
            return articleCountValue;
        },
        set(value) {
            console.log('Count changed.')

            var olyticsLoad = $olytics_dump;

	        olyticsLoad['Action'] = 'Metered article';
	        olyticsLoad['MeterArticle'] = [value];

	        olytics.fire(olyticsLoad);

	        articleCountValue = value;
        },
        configurable: true,
        enumerable: true
    });

    Object.defineProperty(window, 'articleMaxed', {
        get() {
            return articleMaxedValue;
        },
        set(value) {
            if (value == true) {
                console.log('Ran out of articles.')

                var olyticsLoad = $olytics_dump;

		        olyticsLoad['Action'] = 'Metered expired';

		        olytics.fire(olyticsLoad);
            }
            articleMaxedValue = value;
        },
        configurable: true,
        enumerable: true
    });
})();
EOD;
}

function olytics_get_course_categories_by_id($course_id) {
    global $wpdb;

    $query = "
        SELECT GROUP_CONCAT(LOWER(t.slug) SEPARATOR ',') AS course_categories
        FROM {$wpdb->prefix}terms AS t
        INNER JOIN {$wpdb->prefix}term_taxonomy AS tt ON t.term_id = tt.term_id
        INNER JOIN {$wpdb->prefix}term_relationships AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
        WHERE tr.object_id = %d
    ";

    $prepared_query = $wpdb->prepare($query, $course_id);
    $course_categories = $wpdb->get_var($prepared_query);

    return !empty($course_categories) ? $course_categories : '';
}

add_action('gpm_after_wp_head', function() {
    echo "\n<!------ /Omeda Assets ----->\n";
    echo implement_omeda_assets();
    echo "\n<!------ Omeda Assets/ ----->\n";

    $sites_omeda_behavior_id = [
        'artistsnetwork.com' => ['behaviorId' => '5235F5789912A4E', 'community' => 'ArtistsNetwork'],
        'interweave.com' => ['behaviorId' => '7677H9123356A8G', 'community' => 'Interweave'],
        'quiltingdaily.com' => ['behaviorId' => '9019J3567790A2I', 'community' => 'Quilting'],
        'sewdaily.com' => ['behaviorId' => '4014E3567790A2D', 'community' => 'SewDaily']
    ];

    $currentServerHost = preg_replace('/^www\./', '', implode('.', array_slice(explode('.', $_SERVER['HTTP_HOST']), -2)));

    $omedaIdForServerHost = $sites_omeda_behavior_id[$currentServerHost]['behaviorId'] ?? false;

    if ($omedaIdForServerHost) {
        echo '<script>';

        global $post;
       
	    $post = get_post(get_the_ID());
        $post_id = $post->ID;
        $post_title = get_the_title($post_id);
        $post_type = get_post_type($post_id);
        $post_categories = !empty(($categories = capture_slug_list(get_queried_object_id()))) ? explode(",", $categories) : [];

        if(strpos($post_type, 'sfwd-') === 0) {
            $video_categories = !empty($video_categories = olytics_get_course_categories_by_id($post_id)) ? explode(",", $video_categories) : [];
            $course_categories = !empty($course_id = get_post_meta($post_id, 'course_id', true)) ? explode(",", olytics_get_course_categories_by_id($course_id)) : [];
            $post_categories = array_merge($post_categories, $video_categories, $course_categories);
        }

        $post_categories = empty($post_categories) ? '' : implode(",", $post_categories);

        $olytics_dump = array(
            'behaviorId' => $omedaIdForServerHost,
            'category' => $post_categories,
            'community' => $sites_omeda_behavior_id[$currentServerHost]['community'],
            'tag' => '',
        );

        if(isset($_GET['ampcategory']) && isset($_GET['amptag'])) {
            $amp_category = $_GET['ampcategory'];
            $amp_tag = $_GET['amptag'];

            $olytics_dump['AMPCategory'] = $amp_category;
            $olytics_dump['AMPTag'] = $amp_tag;
        }

        if (is_singular() && !is_admin()) {
            if(is_account_page()) {
                if (isset($_COOKIE['wp_user_logged_in'])) {
                    $olytics_dump['wplogin'] = "true";
                    setcookie('wp_user_logged_in', '', time() - 3600, '/');
                }
            }

            if (isset($_COOKIE['wp_user_logged_out'])) {
                $olytics_dump['wplogin'] = "false";
                setcookie('wp_user_logged_out', '', time() - 3600, '/');
            }
        }

        // Set PDF details to olytics tracking
        if (isset($_COOKIE['store_products'])) {
            $cookie_value = stripslashes($_COOKIE['store_products']); 
            $decoded_value  = json_decode($cookie_value, true); 
            $olytics_dump['PDF'] = $decoded_value['PDF'];
            if(!empty($decoded_value['Action'])){
                $olytics_dump['Action'] = str_replace("-",' ',$decoded_value['Action']);        
            }  
            setcookie("store_products", "", time() - 3600, "/");
        }
        if (is_page('download-complete') && $_GET['plus'] == "true" && !empty($_GET['id'])) {
            $product = wc_get_product($_GET['id']);            
            if ($product instanceof WC_Product) { 
                $productTitle = $product->get_name();
                $olytics_dump['PDF'] = $productTitle;        
                if (function_exists('gpm_get_product_type_for_olytics')) {                
                    $product_type = gpm_get_product_type_for_olytics($_GET['id']);
                    if (!empty($product_type)) {
                        $olytics_dump['Action'] = str_replace('-', ' ', $product_type . ' Download');
                    }
                }
            }
        }

        $olytics_dump = json_encode($olytics_dump);

        if(isset($_GET['pdf'])) {
            echo "\n<!------ /Omeda PDF Fire ----->\n";
            echo olytics_pdf_fire($olytics_dump);
            echo "\n<!------ /Omeda PDF Fire ----->\n";
        }

        if ($post && $post->post_type === 'product') {
            echo "\n<!------ /Omeda Instant Download ----->\n";
            echo olytics_instant_download_fire($olytics_dump, $post_title);
            echo "\n<!------ /Omeda Instant Download ----->\n";
        }

        if ($post && ($post->post_type === 'sfwd-lessons' || $post->post_type === 'sfwd-topic')) {
            echo "\n<!------ /Omeda Video Play ----->\n";
            echo olytics_lession_video_play_fire($olytics_dump, $post_title);
            echo "\n<!------ /Omeda Video Play ----->\n";
        }

        if ($post && ($post->post_type === 'sfwd-courses' || $post->post_type === 'sfwd-lessons' || $post->post_type === 'sfwd-topic')) {
            echo "\n<!------ /Omeda Course Reviews ----->\n";
            echo olytics_course_review_fire($olytics_dump, $post_title);
            echo "\n<!------ /Omeda Course Reviews ----->\n";
        }

        if ($post && ($post->post_type === 'sfwd-lessons' || $post->post_type === 'sfwd-topic')) {
            echo "\n<!------ /Omeda Track Progress ----->\n";
            echo olytics_lession_track_progress($olytics_dump);
            echo "\n<!------ /Omeda Track Progress ----->\n";
        }

        if (function_exists('is_product') && is_product()) {
            if (!wp_script_is('sweetalert2', 'enqueued')) {
                wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0.19', true );
            }

            echo "\n<!------ /Omeda Reviews ----->\n";
            echo olytics_reviews_fire($olytics_dump, $post_title);
            echo "\n<!------ /Omeda Reviews ----->\n";
        }

        if (is_single()) {
            echo "\n<!------ /Omeda Article Metering ----->\n";
            echo olytics_metered_fire($olytics_dump, $post_title);
            echo "\n<!------ /Omeda Article Metering ----->\n";
        }

        if (is_single()) {
            echo "\n<!------ /Omeda Comment ----->\n";
            echo olytics_comment_fire($olytics_dump, $post_title);
            echo "\n<!------ /Omeda Comment ----->\n";
        }
        if (is_page('membership/ebooks')) {
            echo "\n<!------ /Omeda Comment ----->\n";
            echo olytics_ebook_fire($olytics_dump);
            echo "\n<!------ /Omeda Comment ----->\n";
        }

        if (is_page('membership/digital-magazine-archive')) {
            echo "\n<!------ /Omeda Comment ----->\n";
            echo olytics_digital_mag_fire($olytics_dump);
            echo "\n<!------ /Omeda Comment ----->\n";
        }

        if ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'downloads' ) ) {
            echo "\n<!------ /Omeda Comment ----->\n";
            echo olytics_zip_fire($olytics_dump);
            echo "\n<!------ /Omeda Comment ----->\n";            
        }

        echo "olytics.fire($olytics_dump);";

        echo '</script>';
    }
}, 10);