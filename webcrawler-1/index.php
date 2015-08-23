<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Web Crawler</title>
    </head>
    <body>
        <h1>Web Crawler Project - 1</h1>
        <?php
            include __DIR__.'/qp/qp.php';

            $pages = array('https://pro.beatport.com/genre/deep-house/12/tracks');
            $done = array();


            $ipCount = 0;
            while ($pages) {

                set_time_limit(0);

                $link = array_shift($pages);
                $done[] = $link;
                $content = file_get_contents($link);

                //load qp with content fetched and initialise from body tag
                $htmlqp = htmlqp($content,'body');
                echo '<pre>';
                if($htmlqp->length>0){
                    //we have some data to parse.
                    $tracks = $htmlqp->find('.track');
                    foreach($tracks as $track){
                        echo 'Track Found:'.$track->find('.buk-track-primary-title')->first()->text()."\r\n";
                    }
                    $next_page = $htmlqp->find('.pag-next')->first();
                    foreach ($next_page as $link) {
                        $page = 'https://pro.beatport.com'.$link->attr('href');
                        echo 'Parsing page:'.$page;
                        $pages[] = $page;
                    }
                }

                $ipCount++;

                if($ipCount==3)
                    $pages = false;

            }
        ?>
    </body>
</html>