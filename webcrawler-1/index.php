<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Web Crawler</title>
    </head>
    <body>
        <h1>Web Crawler Project - 1</h1>
        <?php

        function getFile($url){


            $url_hash = md5($url);
            if(file_exists($url_hash)){
                return file_get_contents($url_hash);
            }else{
                $content = file_get_contents($url);
                file_put_contents($url_hash, $content);
                return $content;
            }

        }

            include __DIR__.'/qp/qp.php';

            $pages = array('https://pro.beatport.com/genre/deep-house/12/tracks');
            $done = array();

            echo '<pre>';
            $ipCount = 0;
            while ($pages) {

                set_time_limit(0);

                $link = array_shift($pages);
                $done[] = $link;
                //$content = file_get_contents($link);
                $content = getFile($link);

                //load qp with content fetched and initialise from body tag
                $htmlqp = @htmlqp($content,'body');

                if($htmlqp->length>0){
                    //we have some data to parse.
                    $tracks = $htmlqp->find('.track');
                    foreach($tracks as $track){
                        echo 'Track Found:'.$track->find('.buk-track-primary-title')->first()->text(). '(' . $track->find('.buk-track-artists > a')->first()->text() . ")\r\n";

                    }
                    $next_page = $htmlqp->find('.pag-next');
                    if($next_page->length>0){
                        $pages = array('https://pro.beatport.com'.$next_page->first()->attr('href'));
                    }else
                        $pages = false;
                }

                $ipCount++;

                if($ipCount==2)
                    $pages = false;

            }
        ?>
    </body>
</html>