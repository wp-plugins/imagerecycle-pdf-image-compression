<?php

defined( 'ABSPATH' ) || die( 'No direct script access allowed!' );

class wpImageRecycle {
    
    private $allowed_ext = array('jpg','png','gif','pdf');
    private $allowedPath = array('wp-content/uploads','wp-content/themes');


    protected $totalImages = 0;
    protected  $totalOptimizedImages = 0;


    public function __construct() {
	include_once 'ioa.class.php';
	
	//Get settings
	$this->settings = get_option( '_wpio_settings' );
	
	//Add column in media manager
	add_filter('manage_media_columns', array(&$this,'addMediaColumn'));
	
	//process files during upload
	add_filter('wp_generate_attachment_metadata', array(&$this,'generateMetadata'));
	
	//Add content in column media manager
	add_action('manage_media_custom_column', array(&$this,'fillMediaColumn'), 10, 2 );
	
	add_action('admin_menu',array(&$this,'wpio_add_menu_page'));
	add_action( 'load-dashboard_page_wpir-foldertree', array(&$this,'wpir_foldertree_thickbox') ); 
	add_action('wp_ajax_wpio_optimize', array(&$this,'doActionOptimize'));
	add_action('wp_ajax_wpio_optimize_all', array(&$this,'doActionOptimizeAll'));
	add_action('wp_ajax_wpio_revert', array(&$this,'doActionRevert'));
	add_action('wp_ajax_wpio_getFolders', array($this, 'getFolders') );
        add_action('wp_ajax_wpio_setFolders', array($this, 'setFolders') );
	add_action('admin_enqueue_scripts', array(&$this,'addScriptUploadPage'));
        add_action('admin_init', array(&$this,'wpio_admin_init'));
        add_action('wp_ajax_wpio_createAccount', array(&$this,'saveNewAccountData'));
    }   
    
    public static function install(){
	global $wpdb;
   
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	$sql = "CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."wpio_images` (
		   `id` int(11) NOT NULL AUTO_INCREMENT,
		   `file` varchar(250) NOT NULL,
		   `md5` varchar(32) NOT NULL,
		   `api_id` int(11) NOT NULL,
		   `size_before` int(11) NOT NULL,
		   `size_after` int(11) NOT NULL,
		   `date` datetime NOT NULL,
                   `expiration_date` datetime NOT NULL,
		   PRIMARY KEY (`id`)
		);";
	dbDelta( $sql );
    }
    
    public function uninstall(){
	
    }
    
    public function wpio_add_menu_page(){
	//Add menu link in the media section
	add_media_page( 'Image Recycle', 'Image recycle', 'activate_plugins', 'wp-image-recycle-page', array(&$this,'showWPImageRecycleMainPage'));
        add_options_page('Image Recycle', 'Image Recycle', 'manage_options', 'option-image-recycle', array( $this, 'view_image_recycle' ));
        add_submenu_page( null, 'Folder tree', 'Folder tree', 'manage_options', 'wpir-foldertree', array( $this, 'folderTree' ) );
        
    }
    
    public function wpio_admin_init(){
        register_setting('Image Recycle','_wpio_settings');
        add_settings_section('wp-image-recycle-page','',array( $this, 'showSettings' ),'option-image-recycle');  
        //add_settings_field('wpio_api_url', __('API Url : ' , 'wpio'), array( $this, 'showAPIUrl' ),'option-image-recycle','wp-image-recycle-page');      
        add_settings_field('wpio_api_key', __('API Key : ', 'wpio'), array( $this, 'showAPIKey' ), 'option-image-recycle', 'wp-image-recycle-page');      
        add_settings_field('wpio_api_secret', __('API Secret : ', 'wpio'), array( $this, 'showAPISecret' ), 'option-image-recycle', 'wp-image-recycle-page');      
        
        add_settings_field('wpio_api_include', __('Include folders : ', 'wpio'), array( $this, 'showIncludeFolder' ), 'option-image-recycle', 'wp-image-recycle-page');      
        add_settings_field('wpio_api_resize_auto', __('Image resize : ', 'wpio'), array( $this, 'showImageResize' ), 'option-image-recycle', 'wp-image-recycle-page');      
        add_settings_field('wpio_api_maxsize', __('Image resize, max size (px) : ', 'wpio'), array( $this, 'showmaxsize' ), 'option-image-recycle', 'wp-image-recycle-page');      
        add_settings_field('wpio_api_minfilesize', __('Min file size to optimize (Kb) : ', 'wpio'), array( $this, 'showminfilesize' ), 'option-image-recycle', 'wp-image-recycle-page');      
        add_settings_field('wpio_api_maxfilesize', __('Max file size to optimize (Kb) : ', 'wpio'), array( $this, 'showmaxfilesize' ), 'option-image-recycle', 'wp-image-recycle-page');      
        
        add_settings_field('wpio_api_typepdf', __('compression type - PDF : ', 'wpio'), array( $this, 'showtypepdf' ), 'option-image-recycle', 'wp-image-recycle-page');      
        add_settings_field('wpio_api_typepng', __('compression type - PNG : ', 'wpio'), array( $this, 'showtypepng' ), 'option-image-recycle', 'wp-image-recycle-page');      
        add_settings_field('wpio_api_typejpg', __('compression type - JPG : ', 'wpio'), array( $this, 'showtypejpg' ), 'option-image-recycle', 'wp-image-recycle-page');      
        add_settings_field('wpio_api_typegif', __('compression type - GIF : ', 'wpio'), array( $this, 'showtypegif' ), 'option-image-recycle', 'wp-image-recycle-page');      
        
    }
    
    public function folderTree() {
       /* Do nothing */
    }
    public function wpir_foldertree_thickbox() {
        if(!defined('IFRAME_REQUEST')) {
            define('IFRAME_REQUEST',true);
        }
        iframe_header(); 
        global $wp_scripts, $wp_styles;
        if(WP_DEBUG) {
            wp_enqueue_script('wp-image-optimizer-jaofoldertree',plugins_url('/js/jaofoldertree.js',dirname(__FILE__)),array(), WPIO_IMAGERECYCLE_VERSION.'-'.  rand(1,1000));
            wp_enqueue_style('wp-image-optimizer-jaofoldertree-css',plugins_url('/css/jaofoldertree.css',dirname(__FILE__)),array(), WPIO_IMAGERECYCLE_VERSION.'-'.  rand(1,1000));
        }else {
            wp_enqueue_script('wp-image-optimizer-jaofoldertree',plugins_url('/js/jaofoldertree.js',dirname(__FILE__)),array(), WPIO_IMAGERECYCLE_VERSION);
            wp_enqueue_style('wp-image-optimizer-jaofoldertree-css',plugins_url('/css/jaofoldertree.css',dirname(__FILE__)),array(), WPIO_IMAGERECYCLE_VERSION);
        }
        
        $include_folders = isset( $this->settings['wpio_api_include'] ) ? $this->settings['wpio_api_include'] : 'wp-content/uploads,wp-content/themes';
        $selected_folders = explode(',',$include_folders );
       ?>
<div style="padding-top: 10px;">
    <div class="pull-left" style="float: left">  
      <div id="wpio_foldertree"></div>
    </div>
    <div class="pull-right" style="float: right;margin-right: 10px;">	
            <button class="button button-primary" type="button" onclick="jSelectFolders()"><?php echo  __('OK','wpio')  ?></button>
            <button class="button" type="button" onclick="window.parent.tb_remove();"><?php echo __('Cancel','wpio')  ?></button>
    </div>
</div>  
<script>
var curFolders = <?php echo json_encode($selected_folders);?>;

jQuery(document).ready(function($) {
   var sdir = '/';
  jSelectFolders = function() {
    
      var fchecked = [];    
       curFolders.sort();
       for(i=0;i< curFolders.length;i++) {
           curDir = curFolders[i];
           valid = true;
           for(j=0;j<i;j++) {
               if(curDir.indexOf(curFolders[j])==0) {
                 valid = false;
               }
           }          
           if(valid) {
                fchecked.push(curDir);
           }
       }
        
       data ={};
       data.folders = fchecked.join(',');
       data.action = 'wpio_setFolders';
       $.ajax({
            url     :  ajaxurl,             
            type    :   "POST",
            data    : data
       }).done(function(result){         
           window.parent.tb_remove();
       });
       
       window.parent.document.getElementById('wpio_api_inxclude').value = fchecked.join(',');
       window.parent.document.getElementById('wpio_api_inxclude_id').value = fchecked.join(',');
  }    
   $('#wpio_foldertree').wpio_jaofoldertree({ 
            script  : ajaxurl,
            usecheckboxes : true,
            showroot : '/',
            oncheck: function(elem,checked,type,file){                     
                var dir = file;
                if(file.substring(file.length-1) ==  sdir) {
                    file = file.substring(0,file.length-1);
                }
                if(file.substring(0,1) ==  sdir) {
                    file = file.substring(1,file.length);
                }         
                if(checked ) {                  
                    if(file!="" && curFolders.indexOf(file)== -1) {
                        curFolders.push(file);
                    }                  
                } else {
                     
                    if(file != "" && !$(elem).next().hasClass('pchecked') ) {
                        temp = []; 
                        for(i=0;i<curFolders.length;i++) {
                            curDir = curFolders[i];
                            if(curDir.indexOf(file)!==0) {
                                temp.push(curDir);
                            }
                        }                        
                        curFolders = temp;   
                    } else {                        
                       var index  = curFolders.indexOf(file);   
                       if(index>-1) {
                            curFolders.splice(index,1);
                       }                        
                    }                    
                }
             
            }
        });
        
        jQuery('#wpio_foldertree').bind('afteropen',function(){
            jQuery(jQuery('#wpio_foldertree').wpio_jaofoldertree('getchecked')).each(function() {
                  curDir = this.file;
                   if(curDir.substring(curDir.length-1) ==  sdir) {
                        curDir = curDir.substring(0,curDir.length-1);
                    }
                    if(curDir.substring(0,1) ==  sdir) {
                        curDir = curDir.substring(1,curDir.length);
                    }
                    if(curFolders.indexOf(curDir)== -1) {
                        curFolders.push(curDir);
                    }
            })
            spanCheckInit();
          
        })
        
    spanCheckInit = function() {        
        $("span.check").unbind('click');
        $("span.check").bind('click', function() {
            $(this).removeClass('pchecked');
            $(this).toggleClass('checked');
            if($(this).hasClass('checked')) {
                $(this).prev().prop('checked', true).trigger('change');;
            }else {
                $(this).prev().prop('checked',false).trigger('change');;
            }
            setParentState(this);
            setChildrenState(this);
        });
    }
    
    setParentState = function(obj) {        
        var liObj = $(obj).parent().parent(); //ul.jaofoldertree
        var noCheck = 0, noUncheck =0, totalEl = 0;
        liObj.find('li span.check').each(function(){
           
            if($(this).hasClass('checked')) {
                noCheck++;
            }else {
                noUncheck++;
            }
            totalEl++;
        })
       
        if(totalEl==noCheck) {
            liObj.parent().children('span.check').removeClass('pchecked').addClass('checked');
            liObj.parent().children('input[type="checkbox"]').prop('checked',true).trigger('change');            
        }else if(totalEl==noUncheck) {
            liObj.parent().children('span.check').removeClass('pchecked').removeClass('checked');
            liObj.parent().children('input[type="checkbox"]').prop('checked',false).trigger('change');            
        }else {
            liObj.parent().children('span.check').removeClass('checked').addClass('pchecked');
            liObj.parent().children('input[type="checkbox"]').prop('checked',false).trigger('change');            
        }
        
        if(liObj.parent().children('span.check').length>0) {           
            setParentState(liObj.parent().children('span.check'));
        }
    }
    
    setChildrenState = function(obj) {        
        if($(obj).hasClass('checked')) {            
            $(obj).parent().find('li span.check').removeClass('pchecked').addClass("checked");
            $(obj).parent().find('li input[type="checkbox"]').prop('checked',true).trigger('change');
        }else {
            $(obj).parent().find('li span.check').removeClass("checked");
            $(obj).parent().find('li input[type="checkbox"]').prop('checked',false).trigger('change');            
        }
        
    }    
})
</script>   
<?php
        iframe_footer(); 
        exit; //Die to prevent the page continueing loading and adding the admin menu's etc. 
    }
    public function view_image_recycle()
    {
        ?>
        <style>.wpio-wrap tr th{width: 250px;}</style>
        <div class="wrap wpio-wrap">
        <?php             
           if( empty($this->settings['wpio_api_key']) || empty($this->settings['wpio_api_secret']) ) {
            include_once WPIO_IMAGERECYCLE .'class/pages/wpio-dashboard.php'; 
           } ?>
            <h2 id="wpio_settings"><?php _e('Image Recycle Settings','wpio') ?></h2>	    
            <form method="post" action="options.php">
            <?php
                settings_fields( 'Image Recycle' );   
                do_settings_sections( 'option-image-recycle' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }
    
    
    public function showSettings(){
	echo 'WP Image Optimizer';
    }
    
    public function showAPIUrl(){
	$api_url = isset( $this->settings['wpio_api_url'] ) ? $this->settings['wpio_api_url'] : 'https://api.imagerecycle.com/v1/';
	echo '<input id="wpio_api_url" name="_wpio_settings[wpio_api_url]" type="text" value="'.esc_attr( $api_url ).'" size="50"/>';
    }
    public function showAPIKey(){
	$api_key = isset( $this->settings['wpio_api_key'] ) ? $this->settings['wpio_api_key'] : '';
	echo '<input id="wpio_api_key" name="_wpio_settings[wpio_api_key]" type="text" value="'.esc_attr( $api_key ).'" size="50"/>';
        
        $installed_time = isset( $this->settings['wpio_api_installed_time'] ) ? $this->settings['wpio_api_installed_time'] : time();        
        echo '<input type="hidden" name="_wpio_settings[wpio_api_installed_time]" value="' . $installed_time . '" />';
        
    }    
    public function showAPISecret(){
	$api_secret = isset( $this->settings['wpio_api_secret'] ) ? $this->settings['wpio_api_secret'] : '';
	echo '<input id="wpio_api_secret" name="_wpio_settings[wpio_api_secret]" type="text" value="'.esc_attr( $api_secret).'" size="50"/>';
    }
    
    public function showIncludeFolder(){
        $api_include = isset( $this->settings['wpio_api_include'] ) ? $this->settings['wpio_api_include'] : 'wp-content'.DIRECTORY_SEPARATOR.'uploads,wp-content'.DIRECTORY_SEPARATOR.'themes';
	echo '<input id="wpio_api_inxclude" readonly type="text" value="'.esc_attr( $api_include).'" size="50"/>';
        echo '<input id="wpio_api_inxclude_id" name="_wpio_settings[wpio_api_include]" type="hidden" value="'.esc_attr( $api_include).'" size="50"/>';
        echo '<a href="index.php?page=wpir-foldertree&TB_iframe=true&width=600&height=550"  class="thickbox"><span class="dashicons dashicons-portfolio" style="line-height:1.5;text-decoration:none"></span></a>';
        wp_enqueue_script( 'thickbox' ); 
        wp_enqueue_style( 'thickbox' ); 
    }
    
    public function showImageResize(){
        $api_imageresize = isset( $this->settings['wpio_api_resize_auto'] ) ? $this->settings['wpio_api_resize_auto'] : 0;
        if($api_imageresize == 1){
            echo '<label><input id="wpio_api_resize_auto_yes" name="_wpio_settings[wpio_api_resize_auto]" type="radio" value="1" checked>'. __('Yes','wpio') .'</label>'.
                    '<label style="margin-left:15px"><input id="wpio_api_resize_auto_no" name="_wpio_settings[wpio_api_resize_auto]" type="radio" value="0">'. __('No','wpio') .'</label>';
        }else{
            echo '<label><input id="wpio_api_resize_auto_yes" name="_wpio_settings[wpio_api_resize_auto]" type="radio" value="1">'. __('Yes','wpio') .'</label>'.
                    '<label style="margin-left:15px"><input id="wpio_api_resize_auto_no" name="_wpio_settings[wpio_api_resize_auto]" type="radio" value="0" checked>'. __('No','wpio') .'</label>';
        }
    }
    
    public function showmaxsize(){
        $api_maxsize = isset( $this->settings['wpio_api_maxsize'] ) ? $this->settings['wpio_api_maxsize'] : '1600';
        $maxsize_input = '<input id="wpio_api_maxsize" name="_wpio_settings[wpio_api_maxsize]" type="text" value="'.esc_attr( $api_maxsize).'" size="10"/>';
        $maxsize_input .= '<p class="description">'.__('Use with caution ! Resize all images regarding the max specified size ie. if 1600px the max width image size will be 1600px','wpio').'</p>';
	echo $maxsize_input;
    }
    
    public function showminfilesize(){
        $api_minfilesize = isset( $this->settings['wpio_api_minfilesize'] ) ? $this->settings['wpio_api_minfilesize'] : '1';
	echo '<input id="wpio_api_minfilesize" name="_wpio_settings[wpio_api_minfilesize]" type="text" value="'.esc_attr( $api_minfilesize).'" size="10"/>';
    }
    
    public function showmaxfilesize(){
        $api_maxfilesize = isset( $this->settings['wpio_api_maxfilesize'] ) ? $this->settings['wpio_api_maxfilesize'] : '5120';
	echo '<input id="wpio_api_maxfilesize" name="_wpio_settings[wpio_api_maxfilesize]" type="text" value="'.esc_attr( $api_maxfilesize).'" size="10"/>';
    }
    
    public function wpio_viewselect($viewId,$viewName,$value){
        $option_array = array('lossy' => __('Best saving','wpio') , 'lossless' => __('Original quality','wpio') , 'none' => __('No compression','wpio'));
        $select = "<select id='$viewId' name='$viewName'>";
        foreach ($option_array as $key => $option){
            if($key == $value){
                $select .= "<option value='$key' selected>$option</option>";
            }else{
                $select .= "<option value='$key'>$option</option>";
            }
        }
        $select .='</select>';
        return $select;
    }
    
    public function showtypepdf(){
        $api_typepdf = isset( $this->settings['wpio_api_typepdf'] ) ? $this->settings['wpio_api_typepdf'] : 'lossy';
        $typepdf = $this->wpio_viewselect('wpio_api_typepdf','_wpio_settings[wpio_api_typepdf]',$api_typepdf);
	echo $typepdf;
    }
    
    public function showtypepng(){
        $api_typepng = isset( $this->settings['wpio_api_typepng'] ) ? $this->settings['wpio_api_typepng'] : 'lossy';
        $typepng = $this->wpio_viewselect('wpio_api_typepng','_wpio_settings[wpio_api_typepng]',$api_typepng);
	echo $typepng;
    }
    
    public function showtypejpg(){
        $api_typejpg = isset( $this->settings['wpio_api_typejpg'] ) ? $this->settings['wpio_api_typejpg'] : 'lossy';
        $typejpg = $this->wpio_viewselect('wpio_api_typejpg','_wpio_settings[wpio_api_typejpg]',$api_typejpg);
	echo $typejpg;
    }
    
    public function showtypegif(){
        $api_typegif = isset( $this->settings['wpio_api_typegif'] ) ? $this->settings['wpio_api_typegif'] : 'lossy';
        $typegif = $this->wpio_viewselect('wpio_api_typegif','_wpio_settings[wpio_api_typegif]',$api_typegif);
	echo $typegif;
    }
    
    public function showWPImageRecycleMainPage(){
	//Proceed actions if needed
	wp_enqueue_script('wp-image-optimizer',plugins_url('js/script.js',dirname(__FILE__)),array(),WPIO_IMAGERECYCLE_VERSION );
	wp_enqueue_style('wp-image-optimizer',plugins_url('css/style.css',dirname(__FILE__)),array(),WPIO_IMAGERECYCLE_VERSION);
        //reset list fail files in session
        if(isset($_SESSION['wpir_failFiles']) ) {
            $_SESSION['wpir_failFiles']= array(); 
        }
        if(isset($_SESSION['wpir_processed']) ) {
            $_SESSION['wpir_processed'] = 0;               
        }
	$images = $this->getLocalImages();
	$images = $this->prepareLocalImages($images);

	if(isset($_GET['paged'])){
	    $paged = (int)$_GET['paged'];
	}else{
	    $paged = 1;
	}
	
	$imagesPaged = array_slice($images, ($paged-1)*30, 30);
		
        if( empty($this->settings['wpio_api_key']) || empty($this->settings['wpio_api_secret']) ) {
            include_once WPIO_IMAGERECYCLE .'class/pages/wpio-dashboard.php'; 
        }else{ 
	    echo '<h1>ImageRecycle - images and pdf compression</h1>';
	    if(isset($_GET['iomess']) && $_GET['iomess']==='accountCreated'){
		echo '<div class="updated notice notice-success is-dismissible below-h2"><p>Your account has been created and your API key and secret automatically filled.  You\'re ready to optimize your images.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dissmiss.</span></button></div>';
	    }
	    $table = new WPIOTable();
	    $table->setColumns(array( 'cb' => '<input type="checkbox" />', 'thumbnail'=>'Image' ,'filename'=>'Filename','size'=>'Size (Kb)','status'=>'Status','actions'=>'Actions'));
	    $table->setItems($imagesPaged,count($images),30);
	    $table->display();
	    if($this->totalImages==0) $this->totalImages = 1; //avoid divide zero
		$progressVal = floor($this->totalOptimizedImages*100 / $this->totalImages);
		if($progressVal>100) $progressVal =100;
		$pressMsg = sprintf("Processing ... %s / %s images", $this->totalOptimizedImages, $this->totalImages);
	    ?>
		<div id="progress_init" style="display: none">
		    <progress value="<?php echo $progressVal;?>" max="100"></progress><span><?php echo $pressMsg;?></span>
		    <p class="timeRemain"></p>
		</div>
	    <?php
	}
    }
    
    protected function getLocalImages(){
	global $wpdb;
        $query = 'SELECT file,api_id,size_before,date,expiration_date FROM '.$wpdb->prefix.'wpio_images';
        $optimizedFiles = $wpdb->get_results($query,OBJECT_K);
	$this->totalOptimizedImages = count($optimizedFiles);	
	$include_folders = isset( $this->settings['wpio_api_include'] ) ? $this->settings['wpio_api_include'] : 'wp-content/uploads,wp-content/themes';
	$this->allowedPath = explode(',',$include_folders);                
        for($i=0;$i<count($this->allowed_ext); $i++) {
            $compression_type = isset($this->settings['wpio_api_type'.$this->allowed_ext[$i]])? $this->settings['wpio_api_type'.$this->allowed_ext[$i]] : "none" ;  
            if($compression_type=="none") {
                unset($this->allowed_ext[$i]);
            }
        }
        $this->allowed_ext = array_values($this->allowed_ext);
        
        $min_size = (int)$this->settings['wpio_api_minfilesize'] *1024;   
        $max_size = (int)$this->settings['wpio_api_maxfilesize'] *1024; 
        if($max_size==0) $max_size = 5 * 1024 * 1024;
        $now = time();
	$images = array();
        foreach ($this->allowedPath as $cur_dir) {
            $scan_dir = str_replace('/', DIRECTORY_SEPARATOR, ABSPATH.$cur_dir) ; 
            foreach (new RecursiveIteratorIterator(new IgnorantRecursiveDirectoryIterator($scan_dir)) as $filename){
                $continue = false;              
                if($continue===true){
                    continue;
                }

                if(!in_array(strtolower(pathinfo($filename,PATHINFO_EXTENSION)),$this->allowed_ext)){
                    continue;
                }	
                if(filesize($filename) < $min_size || filesize($filename) > $max_size) {
                      continue;
                }

                $data = array();
                $data['filename'] = DIRECTORY_SEPARATOR.substr($filename, strlen(ABSPATH));
                $data['size'] = filesize($filename);
                if(isset($optimizedFiles[$data['filename']])){
                    $data['optimized'] = true;
                    $data['optimized_datas'] = $optimizedFiles[$data['filename']];
                    $expirationTime = strtotime($optimizedFiles[$data['filename']]->expiration_date);
                    if($expirationTime < $now) {
                        $data['optimized_datas']->expired = true;                        
                    }                    
                } else{
                    $data['optimized'] = false;
                }
                $this->totalImages++;
                $images[] = $data;
            }
        }
	return $images;
    }

    protected function prepareLocalImages($images){	
	$preparedImages = array();
	foreach ($images as $image){	    
		$data = array();
		$data['filename'] = $image['filename'];
		$data['size'] = number_format(filesize(ABSPATH.$image['filename'])/1000, 2, '.', '') ;
		if($image['optimized'] === true){
		    $data['status'] = '<span class="spinner"></span><span class="optimizationStatus">Optimized at '.round(($image['optimized_datas']->size_before-filesize(ABSPATH.$image['filename']))/$image['optimized_datas']->size_before*100,2).'%</span>';
                    if(isset($image['optimized_datas']->expired) && $image['optimized_datas']->expired ) {
                        $data['actions'] =  '';   
                    }else {
                        $data['actions'] = '<a class="button ioa-proceed" data-action="wpio_revert" data-file="'.$image['optimized_datas']->file.'">Revert to original</a>';
                    }
		    
		}else{
		    $data['status'] = '<span class="spinner"></span><span class="optimizationStatus"></span>';
		    $data['actions'] = '<a class="button button-primary ioa-proceed" data-action="wpio_optimize" data-file="'.$image['filename'].'">Optimize</a>';
		}
		$preparedImages[] = $data;	    
	}
	return $preparedImages;
    }

    public function addMediaColumn( $columns ) {
	$columns['wp-image-recycle'] = __('Image recycle','wp-image-recycle');
	return $columns;
    }
    
    public function fillMediaColumn( $column_name, $id ) {
	switch ( $column_name ) {
	    case 'wp-image-recycle' :
		global $wpdb;
		$meta = wp_get_attachment_metadata( $id );
		$relativePath = '/wp-content/uploads/'.$meta['file'];
		$query = $wpdb->prepare('SELECT * FROM '.$wpdb->prefix.'wpio_images WHERE file=%s',$relativePath);
		$row = $wpdb->get_row($query, OBJECT);
		if(!$row){
		    echo '<span class="optimizationStatus"></span><br/>';
		    echo '<a class="button button-primary ioa-proceed" data-action="wpio_optimize" data-file="'.'/wp-content/uploads/'.$meta['file'].'"><span class="spinner"></span>Optimize</a>';
		}else{
		    echo '<span class="optimizationStatus">Optimized at '.round(($row->size_before-$row->size_after)/$row->size_before*100,2).'%</span><br/>';
		    echo  '<a class="button ioa-proceed" data-action="wpio_revert" data-file="'.$row->file.'"><span class="spinner"></span>Revert to original</a>';
		}
	    break;
	}
    }
    
    function addScriptUploadPage($page) {
	if ( $page === 'settings_page_option-image-recycle' || $page === 'upload.php') {		
		wp_enqueue_script('wp-image-optimizer',plugins_url('js/script.js',dirname(__FILE__)));
	}
    }

    
    protected function optimize($file){
	//Optimization action
	global $wpdb;
	$response = new stdClass();
        $response->status = false;
        $response->errCode = 0;
        $response->msg =  __('Not be optimized yet','wpio') ;
        
	$file = realpath($file);
	$relativePath = DIRECTORY_SEPARATOR.substr($file,strlen(ABSPATH));        
	if($file===false || strpos($file, str_replace("/", DIRECTORY_SEPARATOR, ABSPATH)) !== 0){ 
            $response->msg =  __('File not found','wpio') ;
	    return $response;
	}
	if(!in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)),$this->allowed_ext)){
	    $response->msg =  __('This file type is not allowed','wpio') ;
	    return $response;
	}
	if(!file_exists($file)){
	    $response->msg =  __('File not found','wpio') ;
	    return $response;
	}

	$query = $wpdb->prepare('SELECT id FROM '.$wpdb->prefix.'wpio_images WHERE file=%s',$relativePath);
	if($wpdb->query($query)===false){
	    return $response;
	}
	
	//if($wpdb->num_rows>0){
	   // return false;
	//}
        
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));          
        $compressionType = $this->settings['wpio_api_type'.$ext];       
        if(empty($compressionType)) {
                $compressionType = 'lossy';
        }   
        if($compressionType=="none" || !in_array($ext,$this->allowed_ext) ) {
            $response->msg =  __('This file type is not allowed','wpio') ;
	    return $response;
        }
        
        $fparams = array("compression_type"=> $compressionType);
          
        $resize_auto = $this->settings['wpio_api_resize_auto'];
        $resize_maxsize = (int)$this->settings['wpio_api_maxsize'];  
        if($resize_auto && $resize_maxsize) {   //Only apply on new images
            $installed_time = (int)$this->settings['wpio_api_installed_time'];       
            if(empty($installed_time)) {             
                $installed_time = time();
                $this->settings['wpio_api_installed_time'] = $installed_time;
                update_option( '_wpio_settings', $this->settings );                                
            }
        
            $size = @getimagesize($file);
            $fileCreated = filectime($file);  
            if($size && ($size[0]> $resize_maxsize) && ($fileCreated > $installed_time) ) {
                $fparams['resize'] =  array("width"=> $resize_maxsize);
            }
        }
        
	$ioa = new ioaphp($this->settings['wpio_api_key'], $this->settings['wpio_api_secret']);
       // $api_url = isset( $this->settings['wpio_api_url'] ) ? $this->settings['wpio_api_url'] : 'https://api.imagerecycle.com/v1/';
	//$ioa->setAPIUrl($api_url);
	$return = $ioa->uploadFile($file,$fparams);
	if($return === false || $return === null || is_string($return) ){
	    $response->msg = $ioa->getLastError();
            $response->errCode = $ioa->getLastErrCode();
            return $response;
	}
	$md5 = md5_file($file);
        clearstatcache();
	$sizebefore = filesize($file);

	$optimizedFileContent = @file_get_contents($return->optimized_url);
	if($optimizedFileContent===false){
	    $response->msg =  __('Optimized url not found','wpio') ;
	    return $response;
	}
	if(file_put_contents($file, $optimizedFileContent)===false){
	    $response->msg =  __('Download optimized image fail','wpio') ;
	    return $response;
	}
        clearstatcache();
	$size_after = filesize($file);
	$query = $wpdb->prepare('INSERT INTO '.$wpdb->prefix.'wpio_images (file,md5,api_id,size_before,size_after,date,expiration_date) 
				    VALUES (%s,%s,%d,%d,%d,%s,%s)',
					    $relativePath,$md5,$return->id,$sizebefore,$size_after,date('Y-m-d H:i:s'),$return->expiration_date);
	if($wpdb->query($query)===false){
	    $response->msg =  __('Save optimized image to db fail','wpio') ;
	    return $response;
	}
        
        $response->status = true;
        $response->msg = sprintf(  __('Optimized at %s%%','wpio') , round(($sizebefore-$size_after)/$sizebefore*100,2));
        
	return $response;
	
    }
    
    public function doActionOptimize(){
	$file = ABSPATH.$_REQUEST['file'];
	$returned = $this->optimize($file);
        $this->ajaxReponse($returned->status, $returned);  
	
    }
    
    public function doActionOptimizeAll(){
	$steps = 1;
	$images = $this->getLocalImages();
        if( !session_id() ) {
           session_start();
         }
        if(!isset($_SESSION['wpir_failFiles']) ) {
            $_SESSION['wpir_failFiles']= array();               
        }
        if(!isset($_SESSION['wpir_processed']) ) {
            $_SESSION['wpir_processed'] = 0;               
        }
        ob_implicit_flush(true);
        @ob_end_flush(); 
	foreach ($images as $image){
	    if($image['optimized']===false && !in_array($image['filename'], $_SESSION['wpir_failFiles']) ){
		if($steps===0){                                   
		    $this->ajaxReponse(true,array('continue'=>true,'totalImages'=>$this->totalImages, 'totalOptimizedImages' => $this->totalOptimizedImages,'processedImages'=>$_SESSION['wpir_processed']));
		}
		$returned = $this->optimize(ABSPATH.$image['filename']); 
		if($returned === false || $returned->status === false){	
                    if($returned->errCode=='401' || $returned->errCode=='403') { // Forbidden or Unauthorized
                        $this->ajaxReponse(false, array('continue' => false, 'errMsg' => $returned->msg) );
                    }
                    $failFiles = (array)$_SESSION['wpir_failFiles'];
                    $failFiles[] = $image['filename'];
                    $_SESSION['wpir_failFiles'] = $failFiles;		   
		}
                $processed = (int)$_SESSION['wpir_processed'];
                $_SESSION['wpir_processed'] = $processed+1;
		$steps--;
	    }
	}
	$this->ajaxReponse(true,array('continue'=>false));
    }

    public function doActionRevert(){
	global $wpdb;
	
	$file = realpath(ABSPATH.$_REQUEST['file']);	
        $relativePath = DIRECTORY_SEPARATOR.substr($file,strlen(ABSPATH));        
	 
	$query = $wpdb->prepare('SELECT * FROM '.$wpdb->prefix.'wpio_images WHERE file=%s',$relativePath);
	if($wpdb->query($query)===false){
	    $this->ajaxReponse(false);
	}
	$row = $wpdb->get_row($query,OBJECT);
	if(!$row){
	    $this->ajaxReponse(false);
	}

	$ioa = new ioaphp($this->settings['wpio_api_key'], $this->settings['wpio_api_secret']);
        $api_url = isset( $this->settings['wpio_api_url'] ) ? $this->settings['wpio_api_url'] : 'https://api.imagerecycle.com/v1/';
	$ioa->setAPIUrl($api_url);	
	$return = $ioa->getImage($row->api_id);

	if(!isset($return->id)){
	    $this->ajaxReponse(false);
	}
	$fileContent = @file_get_contents($return->origin_url);
	if($fileContent===false){
	    $this->ajaxReponse(false);
	}

	if(file_put_contents(ABSPATH.$row->file, $fileContent)===false){
	    $this->ajaxReponse(false);
	}

	$query = $wpdb->prepare('DELETE FROM '.$wpdb->prefix.'wpio_images WHERE file=%s',$relativePath);
	$result = $wpdb->query($query);
	if($result===false){
	    $this->ajaxReponse(false);
	}
	$response = new stdClass();
	$response->filename = $row->file;
	$this->ajaxReponse(true,$response);
    }
    
    public function getFolders() {
             
        $include_folders = isset( $this->settings['wpio_api_include'] ) ? $this->settings['wpio_api_include'] : 'wp-content/uploads,wp-content/themes';
        $selected_folders = explode(',', $include_folders);      
        $path = ABSPATH.DIRECTORY_SEPARATOR;
        $dir = $_REQUEST['dir'];
        
        $return = $dirs =  array();
        if( file_exists($path.$dir) ) {            
                $files = scandir($path.$dir);

                natcasesort($files);
                if( count($files) > 2 ) { // The 2 counts for . and ..
                    // All dirs
                    $baseDir = ltrim(rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $dir),'/'),'/'); 
                    if($baseDir != '') $baseDir .= '/';
                    foreach( $files as $file ) {			
                            if( file_exists($path . $dir . DIRECTORY_SEPARATOR . $file) && $file != '.' && $file != '..' && is_dir($path . $dir. DIRECTORY_SEPARATOR . $file) ) {                                                                    
                              
                                    if(in_array( $baseDir .$file,$selected_folders) ) {
                                        $dirs[] = array('type'=>'dir','dir'=>$dir,'file'=>$file,'checked'=>true);
                                    }else {
                                        $hasSubFolderSelected = false;
                                        foreach ($selected_folders as $selected_folder) {
                                            if(strpos($selected_folder,$baseDir .$file)=== 0) {
                                                $hasSubFolderSelected = true;
                                            }
                                        }
                                        if($hasSubFolderSelected) {
                                           $dirs[] = array('type'=>'dir','dir'=>$dir,'file'=>$file,'pchecked'=>true); 
                                        }else {
                                            $dirs[] = array('type'=>'dir','dir'=>$dir,'file'=>$file);
                                        }
                                        
                                    }
                            }
                    }
                    $return = $dirs;
                }
        }
        echo json_encode( $return );      
        die();
    }
    
    public function setFolders() {
               
        $folders  =$_REQUEST['folders'];
        $settings = get_option( '_wpio_settings' );
        $settings['wpio_api_include'] = $folders;
        $result = update_option('_wpio_settings',$settings);
        
        echo json_encode( $result );       
        die();
    }
    
    public function generateMetadata($meta){
	$path = ABSPATH.pathinfo('/wp-content/uploads/'.$meta['file'], PATHINFO_DIRNAME).'/';
	$this->optimize(ABSPATH.'/wp-content/uploads/'.$meta['file']);	
        if(is_array($meta['sizes']) && count($meta['sizes']) ) {
            foreach($meta['sizes'] as $thumb){
                $this->optimize($path.$thumb['file']);	   
            }
        }
	return $meta;
    }
    
    protected function ajaxReponse($status,$datas=null){
	$response = array('status'=>$status,'datas'=>$datas);
	echo json_encode($response);
	die();
    }
        
    public function saveNewAccountData()
    {
        $key = $_REQUEST['key'];
        $secret = $_REQUEST['secret'];
        $settings = get_option('_wpio_settings');
        $settings['wpio_api_key'] = $key;
        $settings['wpio_api_secret'] = $secret;
        $result = update_option('_wpio_settings', $settings);
        echo json_encode($result);
        die();
    }
}

class IgnorantRecursiveDirectoryIterator extends RecursiveDirectoryIterator { 
    function getChildren() { 
        try { 
            return new IgnorantRecursiveDirectoryIterator($this->getPathname()); 
        } catch(UnexpectedValueException $e) { 
            return new RecursiveArrayIterator(array()); 
        } 
    } 
} 

if( !class_exists( 'WPIOTable' ) ) {
    include_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
    class WPIOTable extends WP_List_Table {
	protected $columns;
        protected $totalItems;

	public function setColumns($columns){
	    $this->columns = (array)$columns;
	}

	public function setItems($items,$totalItems){
	    $this->items = $items;
	    $this->totalItems = $totalItems;
	}

	protected function column_default($item, $columnName) {
	    if(isset($item[$columnName])){
		return $item[$columnName];
	    }	
	}

	public function display(){
	    $this->prepare_items();
	    parent::display();
	}

	public function prepare_items(){
	    $hidden = array();
	    $sortable = array();
	    $this->set_pagination_args( array(
		'total_items' => $this->totalItems,                  //WE have to calculate the total number of items
		'per_page'    => 30                     //WE have to determine how many items to show on a page
	    ));
	    $this->_column_headers = array($this->get_columns(), $hidden, $sortable);
	}


	public function get_columns(){
	    return $this->columns;
	}
    
	public function column_cb($item) {
	    return sprintf(
		'<input type="checkbox" name="image[]" value="%s" />', $item['filename']
	    );    
	}
	
        public function column_thumbnail($item) {
            $fileurl = get_site_url(). '/'. str_replace(DIRECTORY_SEPARATOR, '/', $item['filename']);
	    return sprintf(
		'<img class="image-small" src="%s" />', $fileurl
	    );    
	}
        
	function get_bulk_actions() {
	    $actions = array(
	      'optimize_selected'    => 'Optimize selected',
	      'optimize_all'    => 'Optimize all files'
	    );
	    return $actions;
	}
	
	function extra_tablenav($which)
	{
	    $optimizeAllText = __('OptimizeAll','wpio');
	    if($optimizeAllText == "OptimizeAll")
	    {
	        $optimizeAllText = "Optimize all";
	    }
	    ?>
        <div class="alignleft actions bulkactions">
            <input id="dooptimizeall" class="button button-primary action" type="button" value="<?=$optimizeAllText ?>">
        </div>
        <?php
    }
    }
}
