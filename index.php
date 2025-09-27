<?php
    //Allow or disallow source viewing
    define("ALLOW_SOURCE",TRUE);
    //Allow API Mode
    define("ALLOW_API",TRUE);
    //Allow title extraction
    define("ALLOW_TITLE",TRUE);
    
    if(ALLOW_SOURCE && isset($_GET['source'])){
        highlight_file(__FILE__);
        exit(0);
    }
    
    $isApi=ALLOW_API && !empty($_GET['API']) && $_GET['API']=='1';
    $getTitles=ALLOW_TITLE && !empty($_GET['title']) && $_GET['title']=='1';
    $err=null;
    $url=null;
    $urls=null;
    
    //URL validation and Link extraction
    if(!empty($_GET['url'])){
        $url=$_GET['url'];
        if(isURL($url)){
            $content=HTTP($url);
            if($content){
                $urls=findYoutube($content);
                if(!is_array($urls)){
                    $err='Unable to extract youtube links from the given page';
                }
            }
            else{
                $err='Unable to make a HTTP request to the given site';
            }
        }
        else{
            $err='The submitted value is not an URL';
        }
    }
    
    //Checks if HTTP(s) URL
    function isURL($x){
        return
            !!filter_var($x,FILTER_VALIDATE_URL) &&
            !!preg_match('#https?://#',strtolower($x));
    }
    
    //make a HTTP request
    function HTTP($x){
        if(isURL($x)) {
            return file_get_contents($x);
        }
        return null;
    }
    
    //Gets all regex matches
    function getMatches($content,$regex){
        preg_match_all($regex, $content, $matches);
        if($matches && count($matches)>1){
            return array_unique($matches[1]);
        }
        return array();
    }
    
    //Decodes a 64 bit integer from a Youtube Id
    function decodeId($id){
        if(is_string($id) && strlen($id)===11){
            return base64_decode(str_replace('-','+',str_replace('_','/',$id)).'=');
        }
        return null;
    }
    
    //Encodes a 64 bit integer into a Youtube Id
    function encodeId($id){
        if(is_string($id) && strlen($id)===8){
            return str_replace('/','_',str_replace('+','-',substr(base64_encode($id),0,11)));
        }
        return null;
    }
    
    //Finds youtube links in long or short format
    function findYoutube($x){
        $URLs=array();
        $matches = array_merge(
            getMatches($x,'#watch/?\?v=([\w\-]{11})#'),
            getMatches($x,'#https?://youtu.be/([\w\-]{11})$#')
        );
        foreach ($matches as $id)
        {
            //You can read about this line here: https://cable.ayra.ch/help/#youtube_id
            $id=encodeId(decodeId($id));
            $URLs[]="https://www.youtube.com/watch?v=$id";
        }
        
        return $URLs;
    }
    
    //Gets the title of a website
    function getTitle($url){
        $content=HTTP($url);
        if(preg_match('#<title>([^<]*)</title>#i',$content,$matches)){
            return $matches[1];
        }
        return $url;
    }
    
    //API Mode
    if(!$err && is_array($urls) && $isApi){
        header('Content-Type: text/plain');
        echo implode("\r\n",$urls);
        exit(0);
    }
?>
<!DOCTYPE HTML>
<html lang="en">
    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Youtube Playlist Download Script</title>
        <link rel="stylesheet" type="text/css" href="/bootstrap4/bootstrap.min.css" />
        <script type="text/javascript" src="/bootstrap4/jquery.slim.min.js"></script>
        <script type="text/javascript" src="/bootstrap4/popper.min.js"></script>
        <script type="text/javascript" src="/bootstrap4/bootstrap.min.js"></script>
    </head>

    <body>
        <div class="container">
            <h1>Youtube URL extractor</h1>
            <p>
                This script extracts all the Video URLs from a given Page.<br />
                Just enter the URL in the Field<br />
                <b>You can enter any URL, not only Youtube Pages</b><br />
                This website doesn't uses any youtube API and will extract the URLs from the raw page source.
                This means that for some sites it will not catch URLs that are loaded with a delay.
            </p>
            
            <?php if(!$isApi && $err){ ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($err); ?>
                </div>
            <?php } ?>
            
            <form method="GET" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <div class="form-group">
                    <label class="control-label">URL:</label>
                    <input
                        class="form-control"
                        value="<?php echo htmlspecialchars($url); ?>"
                        type="url"
                        name="url"
                        required
                        pattern="https?://.+"
                        title="HTTP(S) urls only" />
                </div>
                <?php if(ALLOW_API){ ?>
                <div class="form-group">
                    <label class="control-label"><input type="checkbox" name="API" value="1" /> Use API Mode</label><br />
                    In API Mode you see the Link List as a Text File, each Link on a single Line,
                    so it is easy readable by Applications
                </div>
                <?php } if(ALLOW_TITLE){ ?>
                <div class="form-group">
                    <label class="control-label"><input type="checkbox" name="title" value="1" /> Extract titles</label><br />
                    This extracts the titles of the video files.
                    Be aware that this is very slow and will have <b>no effect</b> in API mode.
                </div>
                <?php } ?>
                <div class="form-group">
                    <input type="submit" class="btn btn-primary" value="Get URLs" />
                </div>
            </form>
            <?php
                if(is_array($urls))
                {
                    echo '<h3>Found '.count($urls).' links</h3>';
                    foreach($urls as $yturl){
                        $title=htmlspecialchars($getTitles?getTitle($yturl):$yturl);
                        echo "<a href=\"$yturl\">$title</a><br />\r\n";
                        set_time_limit(10);
                    }
                }
            ?>
            <!-- <a href="?source">View Source code</a> -->
        </div>
    </body>
</html>