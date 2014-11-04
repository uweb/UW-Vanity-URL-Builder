<?php

//     Plugin Name: UW Vanity URL Builder
//     Description: This plugin allows custom vanity urls to be created
//     Version: 1.0
//     Author: UW Web Team
//     Author URI: http://www.washington.edu/marketing/web/


class UW_Vanity_URL_Builder
{

  const title = 'Vanity URLs';

  function __construct()
  {
    add_action('admin_menu', array($this, 'admin_menu'));
    add_action('template_redirect', array($this, 'redirect'));
    wp_enqueue_script('backbone');
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-smoothness', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/themes/smoothness/jquery-ui.css');
  }

  function redirect()
  {
    $vanity_urls = get_option('_vanity_urls');
    $pagename = get_query_var('pagename');

    if ( is_array($vanity_urls) && array_key_exists($pagename, $vanity_urls ) )
    {
      $forward_to = $vanity_urls[$pagename]['to'];
      $url = filter_var($forward_to, FILTER_VALIDATE_URL) === false ?  home_url($forward_to) : $forward_to;
      wp_redirect( $url );
      exit;
    }
    return;
  }

  function save()
  {
      $redirects = array_filter(array_map('array_filter', $_POST['vanity'] )); // magic to remove blank values
      foreach ($redirects as $redirect) {
        $redirect = array_filter(array_map('trim', $redirect)); // remove empty string values
        if ( isset($redirect['from']) && isset($redirect['to']) ) {
          $vanity_urls[strtolower($redirect['from'])]['from'] = trim($redirect['from']);
          $vanity_urls[strtolower($redirect['from'])]['to'] = trim($redirect['to']);
          $vanity_urls[strtolower($redirect['from'])]['created'] = $redirect['created'];
          $vanity_urls[strtolower($redirect['from'])]['expires'] = $redirect['expires'];
          $vanity_urls[strtolower($redirect['from'])]['notes'] = $redirect['notes'];
        }
      }
      update_option('_vanity_urls', $vanity_urls );

  }

  function admin_menu()
  {
    add_options_page(self::title, self::title, 'manage_options', 'vanity_urls', array($this, 'settings_page'));
  }

  function settings_page()
  {
    if ( isset($_POST['vanity']) ) self::save();

    ?>
    <div class="wrap">
      <div id="icon-options-general" class="icon32"><br></div>
      <h2><?php echo self::title; ?></h2>
      <p>
       Add or remove vanity urls for <?php bloginfo(); ?> site. The first column is the vanity url while the second column is the partial or full url to redirect to. All the urls are case insensitive. The expiration date will default to 30 days. Expired redirects will be labeled "Expired" in red but will still work and must be removed manually.
      </p>

      <h3>List of redirects</h3>

    <form action="" method="post">
      <div id="vanity-list">
      </div>

      <p>
        <input id="add-vanity-url" type="button" class="button tagadd" value="Add New">
      </p>

      <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
    </form>

    </div>

<style>
.ui-datepicker-trigger { width:25px; float:left; margin: 0 5px;}
</style>
<script type="text/template" id="redirect-template">
    <div class="redirect" style="background-color:read" data-count=<%= count %> >
    <p>
      <input style="width:10%" name="vanity[<%= count %>][from]" type="text" value="<%= from  %>" class="regular-text url" placeholder="Vanity url"/>
      &rarr;
      <input style="width:40%" name="vanity[<%= count %>][to]" type="text" value="<%= to %>" class="regular-text url" placeholder="Actual url"/>
    </p>
    <p>
      <input name="vanity[<%= count %>][created]" type="hidden" value="<%= created %>"/>
      <input class="expires" name="vanity[<%= count %>][expires]" type="hidden" value="<%= expires %>"/>
      <input class="expires-friendly" type="text" value="<%= friendlyDate %>" size="8" disabled="disabled" />
      <input name="vanity[<%= count %>][notes]" type="text" value="<%= notes %>" class="regular-text url" placeholder="Notes" style="width:40%"/>

      <input class="button delete remove-vanity-url" type="button" style="margin-left:30px;" value="Remove">
    <% if ( expired ) { %>
      <small style="color:red;"> Expired </small>
    <% } %>
    </p>
    <hr/>
    </div>
</script>


    <script type="text/javascript">

    jQuery(window).load(function() {
      jQuery( ".expires" ).datepicker();
    })

      jQuery(document).ready(function($) {

        var DEFAULT_DATE = 30;
        var DATE_FORMAT  = 'm/d/yy'

        $.datepicker.setDefaults({
          showOn: "button",
          buttonImage: "/cms/wp-content/themes/maps/images/date.gif",
          buttonImageOnly: true,
          dateFormat: '@',
          defaultDate: DEFAULT_DATE,
          onSelect : function(dateText, datepicker) {
            var friendlyDate = $.datepicker.formatDate( DATE_FORMAT, new Date( Number(dateText) ) )
            $(this).siblings('.expires-friendly').val( friendlyDate )
          }

        })


        $('#add-vanity-url').click(function() {
          var $list = $('div.redirect')
            , $last = $list.last()

          $('#vanity-list').append(
            _.template( $('#redirect-template').html(), { count : $last.data('count') + 1 || 0, from:null, to:null, created:(new Date().getTime()), expires:null, friendlyDate:null, expired:null, notes:null })
          ).find('.expires').last().datepicker().datepicker('setDate', DEFAULT_DATE)

        })

        $('#vanity-list').on('click', 'input.remove-vanity-url', function() {

          var $this = $(this)
              $list = $('div.redirect')

            if ( $list.length === 1)  {
              $this.attr('disabled', true)
              return;
            }

          $this.closest('.redirect').remove()

        })

        $('#vanity-list').on('blur', 'input.url', function() {
          var $this = $(this)
          $this.val($.trim($this.val()));
        })


        var redirects = <?php echo json_encode(get_option('_vanity_urls')) ?>;


        $.each(redirects, function(index,el) {
          //var expired  = $.datepicker.parseDate('@', el.expires).getTime() - new Date() < 0;
          var expired  = Number(el.expires) - new Date().getTime() < 0
            , friendlyDate = $.datepicker.formatDate( DATE_FORMAT, new Date( Number(el.expires)  ) )

          $('#vanity-list').append(
            _.template( $('#redirect-template').html(), {
              count:index,
              from:el.from,
              to:el.to,
              created:el.created || (new Date().getTime()) ,
              expires:el.expires,
              friendlyDate : friendlyDate,
              expired:expired,
              notes:el.notes
            })
          )
        })

      })

    </script>

    <?php

  }

}

new UW_Vanity_URL_Builder;

