<?php
/**
 * Woocommerce Product Importer Settings
 *
 * @class   WC_Product_Importer_Settings
 */

class WC_Product_Importer_Settings 
{    
    public $maxUploadSize;
    public $maxPostSize;
    public $memoryLimitSize;
    public $uploadMBSize;

    public function __construct()
    {        
        $this->maxUploadSize    = (int)(ini_get('upload_max_filesize'));       
        $this->maxPostSize      = (int)(ini_get('post_max_size'));   
        $this->memoryLimitSize  = (int)(ini_get('memory_limit'));       
        $this->uploadMBSize     = min($this->maxUploadSize, $this->maxPostSize, $this->memoryLimitSize);

        add_action( 'admin_menu', [$this, 'kkarft_product_menu_options'] );
        add_action( 'admin_enqueue_scripts', [$this, 'inject_admin_scripts_styles'] );
        add_action( 'add_meta_boxes', [$this, 'add_custom_content_meta_box'] ); 
        add_action( 'woocommerce_admin_process_product_object', [$this, 'save_product_warehouse_item_name_meta_data'], 10, 1 );
    }    

    public function inject_admin_scripts_styles(){  
        wp_enqueue_style("kkraft-admin-style", plugins_url() . '/karkarft-product-importer/includes/css/admin.css'); 
        wp_enqueue_script('jquery-ui-tabs');     
    }

    public function add_custom_content_meta_box() {  
        add_meta_box( 'custom_item_name_meta_box', __( 'Warehouse Item Name', 'karkarft' ), [$this, 'add_warehouse_item_name_meta_box'], 'product', 'normal', 'default' );  
    }
    public function add_warehouse_item_name_meta_box( $post ){
        $product = wc_get_product($post->ID);
        $itemName = $product->get_meta( '_warehouse_item_name' );
        $html = "<div class=\"product_warehouse_item_name\">";
        $html .= "<input type=\"text\" name=\"warehouse_item_name\" id=\"warehouse_item_name\" value=". esc_html($itemName) ." style=\"width:100%\">";
        $html .= "</div>";
        echo $html;
    }

    public function save_product_warehouse_item_name_meta_data( $product ) {
        if (  isset( $_POST['warehouse_item_name'] ) )
        $product->update_meta_data( '_warehouse_item_name', wp_kses_post( trim($_POST['warehouse_item_name']) ) );
    }

    public function kkarft_product_menu_options(){
        add_submenu_page( 'edit.php?post_type=product', 'Imports', 'Imports', 'administrator', 'kkraft_wc_product_importer', array($this, 'kkarft_wc_product_upload'));
    }

    public function kkarft_wc_product_upload(){ 
        if (isset($_POST['uplaod_csv'])) {
            $fileName = $_FILES['kkarft_file']['name'];
            $fileTempName = $_FILES['kkarft_file']['tmp_name'];
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);       
            $minUploadSize = $this->uploadMBSize * 1000000;

            if (!empty($fileName)) {
                if ($ext == 'csv') {
                    if ($_FILES['kkarft_file']['size'] < $minUploadSize) {
                        $handle = fopen($fileTempName, "r");
                        if ($handle) {
                            while (($row = fgetcsv($handle)) !== false) { 
                                if(count($row) > 6){
                                    $return = 3;
                                } else {
                                    if (empty($fields)) {
                                        $fields = $row; 
                                        continue;
                                    }
                                    $csvProducts = [
                                        'category' => $row[0],
                                        'sku' => $row[1],
                                        'warehouse_item_name' => $row[2],
                                        'title' => $row[3],
                                        'price' => round(ltrim($row[4], '$'), 0),
                                        'description' => $row[5]
                                    ];                         
                                    $productCategories = [];
                                    $product = new WC_Product(wc_get_product_id_by_sku($csvProducts['sku']));  
                                    if(empty($product->id)){   
                                        $arrCategory = term_exists($csvProducts['category'], 'product_cat'); 
                                        if(!empty($arrCategory)){
                                            $productCategories[] = $arrCategory['term_id'];
                                        } else {
                                            $arrTerms = wp_insert_term($csvProducts['category'], 'product_cat');
                                            $productCategories[] = $arrTerms['term_id'];
                                        }
                                        // Create new product object
                                        $objProduct = new WC_Product();                                 
                                    }  else {
                                        $objProduct = wc_get_product( $product->id );                                    
                                        $productCategories = wc_get_product_term_ids( $objProduct->get_id(), 'product_cat' );
                                    }      

                                    $objProduct->set_name($csvProducts['title']);
                                    $objProduct->set_description($csvProducts['description']);
                                    $objProduct->set_status("publish");  // can be publish,draft or any wordpress post status
                                    $objProduct->set_catalog_visibility('visible'); // add the product visibility status
                                    $objProduct->set_sku($csvProducts['sku']); //can be blank in case you don't have sku, but You can't add duplicate sku's
                                    $objProduct->set_price($csvProducts['price']); // set product price
                                    $objProduct->set_regular_price($csvProducts['price']); // set product regular price
                                    $objProduct->set_manage_stock(true); // true or false
                                    $objProduct->set_stock_quantity(1);
                                    $objProduct->set_stock_status('instock'); // in stock or out of stock value
                                    $objProduct->set_sold_individually(false);
                                    $objProduct->set_backorders('no');
                                    $objProduct->set_reviews_allowed(true);
                                    $objProduct->set_category_ids($productCategories); // array of category ids

                                   $product_id = $objProduct->save(); 
                                    update_post_meta( $product_id, '_warehouse_item_name', trim($csvProducts['warehouse_item_name']) );     
                                    if($product_id){
                                        $return = 1;
                                    }                                          
                                }                                 
                            }
                            if($return == 1){
                                echo '<div class="notice notice-success is-dismissible"><p>CSV uploaded successfully.</p></div>'; 
                            } else if($return == 3){
                                echo '<div class="notice notice-error is-dismissible"><p>You uploaded CSV has wrong data set. Please check the Sample CSV and try again.</p></div>';
                            } else {
                                echo '<div class="notice notice-error is-dismissible"><p>Something went wrong. Please try again.</p></div>';
                            }
                        }
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>File size should be less than '.$uploadMBSize.' MB.</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Wrong file format. Your file should be CSV format.</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Choose any CSV file before upload.</p></div>';
            }
        }

        if (isset($_POST['uplaod_image'])) {
            $extension = ["jpeg","jpg","png"];
            $uploadReturn = false;
            foreach($_FILES["kkarft_image_files"]["tmp_name"] as $key => $tmpName) {
                $fileName   =   $_FILES["kkarft_image_files"]["name"][$key];
                $fileTmp    =   $_FILES["kkarft_image_files"]["tmp_name"][$key];
                $ext        =   pathinfo($fileName,PATHINFO_EXTENSION);
                $sku        =   substr($fileName, 0, strpos($fileName, ".".$ext));
                $product    =   new WC_Product(wc_get_product_id_by_sku($sku));   

                /*Upload product image in wordpress upload directory*/
                if(in_array($ext, $extension)) {
                    if($product->id != 0){
                        $upload_dir         = wp_upload_dir();                 
                        $filePath           = $upload_dir['path'] . '/' . $fileName;   
                        if(!file_exists($filePath)) {
                           $uploadReturn    = move_uploaded_file($fileTmp, $filePath);
                        } else {
                            $imageName      = basename($fileName,$ext);
                            $newFileName    = $imageName . "_" . time() . "." . $ext;
                            $filePath       = $upload_dir['path'] . '/' . $newFileName;
                            $uploadReturn   = move_uploaded_file($fileTmp, $filePath);
                        }                                                                   
                        $this->generate_featured_image( $filePath, $product->id ); 
                    } else{                        
                        echo '<div class="notice notice-error is-dismissible"><p>'.$fileName.' image is not assosite with product because SKU [ '.$sku.' ] is not in product store. </p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Image format does not match with the following extension [ '. implode(', ',$extension) .' ] for this product : '.$sku.' </p></div>';
                }
            }
            if($uploadReturn){
                echo '<div class="notice notice-success is-dismissible"><p>Product image uplaoded successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Image upload faild, please try again. </p></div>';
            }
        }   

        $html  = '<div class="kkarft-import-wrapper">';  
        $html .= '<h1>Import Products</h1>';

        $html .= '<div class="kkarft_import_order_tab_section">';
        $html .= '<div class="kkarft_import_order_tabs" id="kkarft_import_order_tabs">';
        $html .= '<ul class="tab-links">';
        $html .= '<li><a href="#kkarft_order_tab-product_data">Upload CSV</a></li>';
        $html .= '<li><a href="#kkarft_order_tab-product_image">Upload Product Images</a></li>';
        $html .= '</ul>';
        $html .= '<div class="tab-content">';

        $html .= '<div id="kkarft_order_tab-product_data" class="kkarft-wc-form-wrapper">';
        $html .= '<form class="wc-progress-form-content woocommerce-importer" method="post" action="edit.php?post_type=product&page=kkraft_wc_product_importer" enctype="multipart/form-data">';
        $html .= '<header> <div class="csv_header_wrap"> <div><h2>Import products from a CSV file</h2>';
        $html .= '<p>This will allows you to import product data to your store from a CSV file.</p> </div>';
        $html .= '<p>Check the CSV format before upload. <a href="'.plugins_url() . '/karkarft-product-importer/includes/sample-product.csv" download> <img src="'.plugins_url() . '/karkarft-product-importer/includes/img/download-icon.png" class="download-icon" >Download sample CSV</a> </p></div> </header>';
        $html .= '<section>';
        $html .= '<div class="uplaod_input_box">';
        $html .= '<label for="upload"> Choose a CSV file from your computer : </label>';
        $html .= '<div><input type="file" name="kkarft_file" id="kkarft_file">';
        $html .= '<br><small> Maximum size: '. $this->uploadMBSize .' MB</small>';
        $html .= '</div></div>';
        $html .= '</section>';
        $html .= '<footer class="wc-actions">';
        $html .= '<button type="submit" class="button button-primary button-next" name="uplaod_csv" value="Upload CSV">Upload</button>';
        $html .= '</footer></form>';
        $html .= '</div>';

        $html .= '<div id="kkarft_order_tab-product_image" class="kkarft-wc-form-wrapper">';
        $html .= '<form class="wc-progress-form-content woocommerce-importer" method="post" action="edit.php?post_type=product&page=kkraft_wc_product_importer" enctype="multipart/form-data">';
        $html .= '<header> <div class="csv_header_wrap"> <h2>Import products images</h2>';
        $html .= '</div> </header>';
        $html .= '<section>';
        $html .= '<div class="uplaod_input_box">';
        $html .= '<label for="upload"> Choose product images : </label>';
        $html .= '<div><input type="file" name="kkarft_image_files[]" id="kkarft_image_files" multiple>';
        $html .= '<br><small> Select multiple images</small>';
        $html .= '</div></div>';
        $html .= '</section>';
        $html .= '<footer class="wc-actions">';
        $html .= '<button type="submit" class="button button-primary button-next" name="uplaod_image" value="Upload Image">Upload Image</button>';
        $html .= '</footer></form>';
        $html .= '</div>';

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';
        $html .='<script>jQuery(document).on(\'ready\', function() { jQuery("#kkarft_import_order_tabs").tabs(); }); </script>';

        echo $html;
    }

    function generate_featured_image( $image_url, $post_id  ){
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url);
        $filename = basename($image_url);
        if(wp_mkdir_p($upload_dir['path']))
          $file = $upload_dir['path'] . '/' . $filename;
        else
          $file = $upload_dir['basedir'] . '/' . $filename;
        file_put_contents($file, $image_data);

        $wp_filetype = wp_check_filetype($filename, null );
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
        $res1= wp_update_attachment_metadata( $attach_id, $attach_data );
        $res2= set_post_thumbnail( $post_id, $attach_id );
    }
}
$obj_wc_product_importer = new WC_Product_Importer_Settings();