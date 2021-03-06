<?php
/*
Plugin Name: F13 Movie Embed Shortcode
Plugin URI: http://f13dev.com/wordpress-plugin-movie-embed-shortcode/
Description: Embed information about a movie or TV show into a WordPress blog post or page using shortcode.
Version: 1.0
Author: Jim Valentine - f13dev
Author URI: http://f13dev.com
Text Domain: f13-movie-embed-shortcode
License: GPLv3
*/

/*
Copyright 2016 James Valentine - f13dev (jv@f13dev.com)
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
any later version.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/

// Register the shortcode
add_shortcode( 'movie', 'f13_movie_shortcode');
// Register the css
add_action( 'wp_enqueue_scripts', 'f13_movie_shortcode_style');

/**
 * A function to register the stylesheet
 * @return [type] [description]
 */
function f13_movie_shortcode_style()
{
    wp_register_style( 'f13movie-style', plugins_url('movie-shortcode.css', __FILE__) );
    wp_enqueue_style( 'f13movie-style' );
}

/**
* Function to handle the shortcode
* @param  Array  $atts    The attributes set in the shortcode
* @param  [type] $content [description]
* @return String          The response of the shortcode
*/
function f13_movie_shortcode( $atts, $content = null )
{
    // Get the attributes
    extract( shortcode_atts ( array (
    'imdb' => '', // The IMDB movie ID
    'title' => '', // The title of the movie
    'type' => '', // The type (movie, series, episode)
    'year' => '', // The year of the movie
    'plot' => 'full', // Return full or short plot
    'cachetime' => '1440', // Cache timeout, default 24 hours
    //'rating' => 'true', // Return rotton tomatoes rating (true, false)
  ), $atts ));

  // Set the cache name for this instance of the shortcode
  $cache = get_transient('f13movie' . md5(serialize($atts)));

  if ($cache)
  {
      // If the cache already exists, return it rather than re-creating it
      return $cache;
  }
  else
  {
    // Check if a title or IMDB ID has been entered
    if ($title == '' && $imdb == '')
    {
      // Notify the user that a Title or IMDB ID is required
      $string = 'In order to use this shortcode either the \'imdb\' or \'title\' attributes must be set.<br/>
      Shortcode example:<br />
      [movie imdb=\'imdb_movie_id\'] or [movie title=\'A movie title\']';
    }
    else
    {
      // Store the search query in a variable
      $query = 'http://www.omdbapi.com/?';

      // Check if an IMDB ID has been entered
      if ($imdb != '')
      {
        // If an IMDB ID is present, add it to the query
        // and send the string, no further attributes are
        // required.
        $query .= 'i=' . $imdb;
      }
      else
      {
        // Add the title to the query string
        if ($title != '')
        {
          $query .= 't=' . str_replace(' ', '%20', $title) . '&';
        }
        // Add the type if it is set to an appropriate value
        if ($type == 'move' || $type == 'series' || $type == 'episode')
        {
          $query .= 'type=' . $type . '&';
        }
        // Add the year if it is set and is a number
        if ($year != '' && is_numeric($year))
        {
          $query .= 'y=' . $year . '&';
        }
        // If the plot attribute is set to short, set it short,
        // otherwise set it to full
        if ($plot == 'short')
        {
          $query .= 'plot=short&';
        }
        else
        {
          $query .= 'plot=full';
        }
      }
      // Get the movie data and store it in a variable
      $movie_data = f13_get_movie_data($query);
      // Send the movie data to be formatted
      $string = f13_format_movie_data($movie_data);
    }
    // Multiply the cache timeout by 60 to convert the time in minutes
    // to the time in seconds
    $cachetime = $cachetime * 60;
    // If the cachetime is 0, set it to 1 for an almost instant timeout
    if ($cachetime == 0)
    {
      $cachetime = 1;
    }
    // Set the cache
    set_transient('f13movie' . md5(serialize($atts)), $string, $cachetime);
    // Return the generated string
    return $string;
  }
}

/**
 * A function to format the movie data into a widget
 * @param  array  $data An array of movie data
 * @return String       A formatted rich text string of movie data
 */
function f13_format_movie_data($data)
{
  // Create a variable to store the formatted rich text data
  $rich_text = '';
  // Open the movie container
  $rich_text .= '<div class="f13-movie-container">';
  // Check if a response was generated
  if ($data['Response'] != 'True')
  {
    // If a response was not generated warn the user
    $rich_text .= '<span class="f13-movie-error">The movie, show or episode you requested could not be found.</span>';
  }
  else
  {
    // Open a head div
    $rich_text .= '<div class=f13-movie-head>';

      // Add the title
      $rich_text .= '<div class="f13-movie-title">' . $data['Title'] . '</div>';
      // If the year is available add it
      if ($data['Year'] != '')
      {
        $year = $data['Year'];
        // Check if the year is a range ending in '-'
        if (substr($year, -1) == '-')
        {
          $year = $year . 'present';
        }
        // Add the year
        $rich_text .= '<div class="f13-movie-year"><span>Year: </span>' . $year . '</div>';
      }
      // If the release date is available add it
      if ($data['Released'] != '')
      {
        $rich_text .= '<div class="f13-movie-released"><span>Release date: </span>' . $data['Released'] . '</div>';
      }
      // If the response is a series, input series specific data
      if ($data['Type'] == 'series')
      {
        // Check if totalSeasons is set, if so add it
        if ($data['totalSeasons'] != '')
        {
          $rich_text .= '<div class="f13-movie-totalSeasons"><span>Totla seasons: </span>' . $data['totalSeasons'] . '</div>';
        }
      }
      // If the response is an episode, input episode specific data
      elseif ($data['Type'] == 'episode')
      {
        // Open an episode div
        $rich_text .= '<div class="f13-movie-episode">';
          // If a season is set, add it
          if ($data['Season'] != '')
          {
            $rich_text .= '<span>Season </span>' . $data['Season'] . ' ';
          }
          // If an episode number is set add it
          if ($data['Episode'] != '')
          {
            $rich_text .= '<span>Episode </span>' . $data['Episode'] . ' ';
          }
        // Close the episode div
        $rich_text .= '</div>';
      }

    // Close the head div
    $rich_text .= '</div>';



    // If a plot is set, add it
    if ($data['Plot'] != '')
    {
      $rich_text .= '<div class="f13-movie-plot">
      <span>Plot: </span>' . $data['Plot'] . '</div>';
    }


    // Open a stats div
    $rich_text .= '<div class="f13-movie-stats">';

      // If the poster exists add it
      if ($data['Poster'] != '')
      {
        // Get the filenmae from the image URL
        $image_name = explode('/', $data['Poster']);
        $image_name = end($image_name);
        // Find if the file already exists in attachments
        $image_id = f13_get_attachment_id($image_name);
        if ($image_id == null)
        {
          // If the image file does not already exist try and
          // add it to the media library.

          // Require files used to sideload
          require_once(ABSPATH . 'wp-admin/includes/media.php');
          require_once(ABSPATH . 'wp-admin/includes/file.php');
          require_once(ABSPATH . 'wp-admin/includes/image.php');

          // Attempt to sideload image
          media_sideload_image($data['Poster'], get_the_ID(), $data['Title']);
          // Get the newly sideloaded image
          $image_id = f13_get_attachment_id($image_name);
          // Get the image url
          $image_url = wp_get_attachment_url($image_id);
        }
        else
        {
          // If the image already exists, use the
          // image id already obtained.
          $image_url = wp_get_attachment_url($image_id);

        }
        // Check if the image id is a number, if so add
        // the image.
        if (is_numeric($image_id) && $image_id != null)
        {

            // Add the image using the pre-found image url
            $rich_text .= '<img src="' . $image_url . '" />';

        }
      }



        // If a runtime is set, add it
        if ($data['Runtime'] != '')
        {
          $rich_text .= '<span>Runtime: </span>' . $data['Runtime'] . '<br/>';
        }
        // If a genre is set, add it
        if ($data['Genre'] != '')
        {
          $rich_text .= '<span>Genre: </span>' . $data['Genre'] . '<br />';
        }
        // If awards is set, add it
        if ($data['Awards'] != 'N/A' && $data['Awards'] != '')
        {
          $rich_text .= '<span>Awards: </span>' . $data['Awards'] . '<br />';
        }

      // If a director is set, add it
      if ($data['Director'] != 'N/A')
      {
        $rich_text .= '<span>Director: </span>' . $data['Director'] . '<br />';
      }
      // If a writer is set, add it
      if ($data['Writer'] != 'N/A')
      {
        $rich_text .= '<span>Writer: </span>' . $data['Writer'] . '<br />';
      }
      // If actors is set, add it
      if ($data['Actors'] != 'N/A')
      {
        $rich_text .= '<span>Actors: </span>' . $data['Actors'] . '<br />';
      }

    // Close the stats div
    $rich_text .= '</div>';

    // Clear floating divs
    $rich_text .= '<br style="clear:both" />';

    // Create a localization div
    $rich_text .= '<div class="f13-movie-localization">';
      // If a language is set, add it
      if ($data['Language'] != 'N/A')
      {
        $rich_text .= '<div class="f13-movie-language"><span>Language: </span>' . $data['Language'] . '</div>';
      }
      // If a country is set, add it
      if ($data['Country'] != 'N/A')
      {
        $rich_text .= '<div class="f13-movie-country"><span>Country: </span>' . $data['Country'] . '</div>';
      }
    // Close the localization div
    $rich_text .= '</div>';

    // Open a rating div
    $rich_text .= '<div class="f13-movie-rating">';
      // Check if a valid rating is set
      if (is_numeric($data['imdbRating']))
      {
        // If a valid rating is set add the IMDB rating title
        $rich_text .= '<span>IMDB Rating: </span>';
        // If imdbVotes is a valid number, append the number of voters
        if (is_numeric(str_replace(',', '', $data['imdbVotes'])))
        {
          $rich_text .= ' ' . $data['imdbRating'] . '/10 from ' . $data['imdbVotes'] . ' votes';
        }
        // Open the stars div
        $rich_text .= '<div class="f13-movie-stars">';
        // Show the IMDB star rating
        $rich_text .= f13_get_movie_rating_stars($data['imdbRating']);
        // Close the stars div
        $rich_text .= '</div>';
      }
      else
      {
        // If no valid rating is set, generate the 0 stars image
        $rich_text .= f13_get_movie_rating_stars(0.0);
      }
    // Close the rating div
    $rich_text .= '</div>';

    // If the IMDB id is present, add a link to the IMDB page
    if ($data['imdbID'] != '' || $data['imdbID'] != 'N/A')
    {
      $rich_text .= '<div class="f13-movie-imdb"><a href="http://www.imdb.com/title/' . $data['imdbID'] . '">View ' . $data['Title'] . ' on IMDB</a></div>';
    }
    $rich_text .= '<div class="f13-movie-powered-by">Data provided by <a href="http://omdbapi.com">OMDb API</a></div>';
  }

  // Close the movie container
  $rich_text .= '</div>';
  return $rich_text;
}

/**
 * A function to retrieve the movie information from the
 * Open Movie Databse
 * @param  $query  The query string to be appended to the url.
 * @return         A decoded array of information about the movie.
 */
 function f13_get_movie_data($url)
 {
   // Start curl
   $curl = curl_init();
   // Set curl options
   curl_setopt($curl, CURLOPT_URL, $url);
   curl_setopt($curl, CURLOPT_HTTPGET, true);
   // Set curl headers
   curl_setopt($curl, CURLOPT_HTTPHEADER, array(
     'Content-Type: application/json',
     'Accept: application/json'
   ));
   // Set the user agent
   curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
   // Set curl to return the response, rather than print it
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
   // Get the results
   $result = curl_exec($curl);
   // Close the curl session
   curl_close($curl);
   // Decode the results
   $result = json_decode($result, true);
   // Return the results
   return $result;
 }

/**
 * A function to generate a star rating image
 * @param  float  $aRating A float representing a rating out of 10
 * @return String          A string of rich data showing a star rating
 */
function f13_get_movie_rating_stars($aRating)
{
  $string = '';
  for ($x = 1; $x < $aRating; $x++ )
  {
    $string .= '<img src="' . plugin_dir_url( __FILE__ ) . 'img/star-full.png" />';
  }
  if (strpos($aRating, '.'))
  {
    $string .= '<img src="' . plugin_dir_url(__FILE__) . 'img/star-half.png" />';
    $x++;
  }
  while ($x <= 10)
  {
    $string .= '<img src="' . plugin_dir_url(__FILE__) . 'img/star-empty.png" />';
    $x++;
  }
  return $string;
}


// retrieves the attachment ID from the filename
function f13_get_attachment_id($file_name) {
	global $wpdb;
  // Search the database for an attachment ending with the filename
	$attachment = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->base_prefix}postmeta WHERE meta_key='_wp_attached_file' AND meta_value LIKE %s;", '%' . $file_name ));
  // Returns the post ID or null
  if ($attachment[0] == null || $attachment[0] == '')
  {
    // If the post ID is not valid return null
    return null;
  }
  else
  {
    // Otherwise return the valid post ID
    return $attachment[0];
  }
}
