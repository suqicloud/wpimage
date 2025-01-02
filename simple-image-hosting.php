<?php
/**
 * Plugin Name: 小半WP图床
 * Plugin URI: https://www.jingxialai.com/4933.html
 * Description: 一个非常简单基于WordPress的图床插件，支持前台图片上传及快捷复制链接功能。
 * Version: 1.2
 * Author: Summer
 * License: GPL License
 * Author URI: https://www.jingxialai.com/
 */

// 防止直接访问
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 插件列表页面添加设置链接
function simple_image_hosting_settings_link($links) {
    $settings_link = '<a href="admin.php?page=simple-image-hosting">设置</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'simple_image_hosting_settings_link');

//1. 创建页面
function simple_image_hosting_menu() {
    add_menu_page(
        '小半WP图床设置页面',
        'WP图床',
        'manage_options',
        'simple-image-hosting',
        'simple_image_hosting_settings_page',
        'dashicons-image-filter',
        20
    );
    // 用户图片查询页面
    add_submenu_page(
        'simple-image-hosting',
        '小半WP图床用户图片查询',
        '图片查询',
        'manage_options',
        'simple-image-hosting-user-images',
        'simple_image_hosting_user_images_page'
    );    
}
add_action( 'admin_menu', 'simple_image_hosting_menu' );

//2. 设置页面内容
function simple_image_hosting_settings_page() {
    ?>
    <div class="wrap">
        <h1>小半WP图床设置</h1>
        
        <?php
        // 如果保存成功，显示自定义消息
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == true) {
            echo '<div id="simple-image-hosting-message" class="updated notice is-dismissible">
                    <p>设置已保存！</p>
                  </div>';
        }
        ?>        
        <form method="post" action="options.php">
            <?php
            settings_fields( 'simple_image_hosting_options_group' );
            do_settings_sections( 'simple-image-hosting' );
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">关闭游客上传权限</th>
                    <td><input type="checkbox" name="simple_image_hosting_disable_guests" value="1" <?php checked( 1, get_option( 'simple_image_hosting_disable_guests' ), true ); ?> /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">用户组权限</th>
                    <td>
                        <?php
                        // 角色和其对应的中文名称
                        $roles = [
                            'subscriber'    => '订阅者',
                            'contributor'   => '贡献者',
                            'author'        => '作者',
                            'editor'        => '编辑',
                            'administrator' => '管理员',
                        ];

                        // 获取用户组设置
                        $user_roles = get_option('simple_image_hosting_user_roles', []); 
                        if (is_string($user_roles)) {
                            // 如果是字符串，将其转换为数组
                            $user_roles = explode(',', $user_roles);
                        }
                        $user_roles = (array) $user_roles; // 强制转换为数组
                        
                        foreach ($roles as $role => $role_name) {
                            ?>
                            <label>
                                <input type="checkbox" name="simple_image_hosting_user_roles[]" value="<?php echo $role; ?>" 
                                    <?php echo in_array($role, $user_roles) ? 'checked' : ''; ?> />
                                <?php echo $role_name . $role; ?>
                            </label><br>
                            <?php
                        }
                        ?>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">允许上传的文件格式</th>
                    <td>
                        <input type="text" name="simple_image_hosting_allowed_formats" value="<?php echo esc_attr( get_option( 'simple_image_hosting_allowed_formats' ) ?: 'jpg,jpeg,png' ); ?>" />
                        <p class="description">允许上传的文件格式，多个格式请用英文逗号隔开（例如：jpg,jpeg,png,gif）。</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">最大上传文件大小(MB)</th>
                    <td>
                        <input type="number" name="simple_image_hosting_max_file_size" value="<?php echo esc_attr( get_option( 'simple_image_hosting_max_file_size', 2 ) ); ?>" min="1" />
                        <p class="description">设置最大文件上传大小，单位：MB，默认为2MB。</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">公告内容</th>
                    <td>
                        <textarea name="simple_image_hosting_announcement" rows="5" cols="50"><?php echo esc_textarea( get_option( 'simple_image_hosting_announcement' ) ); ?></textarea>
                        <p class="description">公告内容，支持HTML代码，可以不填写。</p>
                    </td>
                </tr>

            </table>
            <?php submit_button(); ?>
        </form>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // 检查是否存在自定义消息框
            if ($('#simple-image-hosting-message').length) {
                setTimeout(function() {
                    $('#simple-image-hosting-message').fadeOut();
                }, 1000);  // 延迟1秒隐藏消息
            }
        });
    </script>  
    <?php
}

//3. 注册设置
function simple_image_hosting_register_settings() {
    register_setting( 'simple_image_hosting_options_group', 'simple_image_hosting_disable_guests' );
    //关闭游客上传
    register_setting( 'simple_image_hosting_options_group', 'simple_image_hosting_allowed_formats' );
    //文件格式
    register_setting( 'simple_image_hosting_options_group', 'simple_image_hosting_max_file_size' );
    //文件大小
    register_setting( 'simple_image_hosting_options_group', 'simple_image_hosting_announcement' );
    //公告内容

    //用户组权限
    register_setting( 'simple_image_hosting_options_group', 'simple_image_hosting_user_roles', array(
        'type' => 'array',
        'sanitize_callback' => 'simple_image_hosting_sanitize_roles'
    ));
}
add_action( 'admin_init', 'simple_image_hosting_register_settings' );

//4. 用户角色函数
function simple_image_hosting_sanitize_roles($roles) {
    if (is_array($roles)) {
        return implode(',', $roles); // 将数组转换为逗号分隔的字符串
    }
    return ''; // 如果没有角色选中，返回空字符串
}

//5. 插件激活时创建页面
function simple_image_hosting_activate() {
    // 检查是否已存在图片上传页面
    if ( null === get_page_by_title( '图片上传' ) ) {
        $upload_page = array(
            'post_title'   => '图片上传',
            'post_content' => '[image_upload_form]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        );
        wp_insert_post( $upload_page );
    }

    // 检查是否已存在图片管理页面
    if ( null === get_page_by_title( '图片管理' ) ) {
        $manage_page = array(
            'post_title'   => '图片管理',
            'post_content' => '[image_manage_page]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        );
        wp_insert_post( $manage_page );
    }
}
register_activation_hook( __FILE__, 'simple_image_hosting_activate' );


//6. 注册前端页面
function simple_image_hosting_page() {
    ob_start();

    // 获取文件上传格式
    $allowed_formats = get_option( 'simple_image_hosting_allowed_formats', 'jpg,jpeg,png' ); 
    $allowed_formats = array_map('trim', explode(',', $allowed_formats)); // 转换为数组并去除空格
    $allowed_formats_str = '.' . implode(', .', $allowed_formats); // 转换为以点开头的文件类型字符串

    // 获取最大文件大小（以字节为单位）
    $max_file_size = get_option('simple_image_hosting_max_file_size', 2 * 1024 * 1024);
    // 转换为MB
    $max_file_size_mb = round($max_file_size / 1024 / 1024, 2);

    // 获取公告
    $announcement = get_option( 'simple_image_hosting_announcement', '' );

    // 获取权限设置选项
    $disable_guests = get_option( 'simple_image_hosting_disable_guests', 0 );
    $user_roles = get_option( 'simple_image_hosting_user_roles', [] );
    if (is_string($user_roles)) {
        $user_roles = explode(',', $user_roles);
    }
    $user_roles = (array) $user_roles; 

    // 检测权限
    $message = '';
    $is_logged_in = is_user_logged_in();
    $current_user = wp_get_current_user();

    if ( $disable_guests && ! $is_logged_in ) {
        // 游客未登录，显示提示信息
        $message = '请先登录才能上传图片';
    } elseif ( $is_logged_in && ! array_intersect( $current_user->roles, $user_roles ) ) {
        // 用户组未授权，显示提示信息
        $message = '您没有上传图片的权限，请联系管理员开通';
    }
    // 获取图片管理页面的URL
    $manage_page_url = get_image_manage_page_url();
    ?>
    <div class="simple-image-hosting-container">
        <h2>图片上传</h2>
        <div id="upload-message" class="upload-message"><?php echo esc_html($message); ?></div>
        <?php if ( empty($message) ) : ?>
        <form class="simple-image-hosting-upload-form" enctype="multipart/form-data">
            <label for="image-upload-input">点击选择图片</label>
            <input type="file" id="image-upload-input" name="file" multiple accept="<?php echo esc_attr( $allowed_formats_str ); ?>" />
            <div class="simple-image-hosting-drop-area">
                <p>将图片拖拽到这里(支持多图批量上传)</p>
            </div>
        </form>
        <?php if ( is_user_logged_in() && $manage_page_url ) : ?>
            <a href="<?php echo esc_url( $manage_page_url ); ?>" class="simple-image-manage-btn" target="_blank">进入图片管理页面</a>
        <?php endif; ?>
        <div class="simple-image-hosting-preview-container"></div>
        <button class="simple-image-hosting-copy-all-btn" style="display:none;">一键复制所有图片直连</button>

        <!-- 获取文件格式设置 -->
        <p class="upload-format-info">支持上传的文件格式为：<?php echo esc_html( $allowed_formats_str ); ?>，最大文件大小为：<?php echo $max_file_size; ?>MB</p>

         <!-- 公告内容允许HTML -->
        <?php if ( $announcement ) : ?>
            <div class="simple-image-hosting-announcement">
                <?php echo wp_kses_post( $announcement ); ?>
            </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'image_upload_form', 'simple_image_hosting_page' );


//7. 添加插件的样式和脚本
function simple_image_hosting_assets() {
    // 检查当前页面是否包含image_upload_form短代码或者image_manage_page短代码
    if (is_page() && (has_shortcode(get_post()->post_content, 'image_upload_form') || has_shortcode(get_post()->post_content, 'image_manage_page'))) {
        wp_enqueue_style( 'simple-image-hosting-style', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
        wp_enqueue_script( 'simple-image-hosting-script', plugin_dir_url( __FILE__ ) . 'assets/script.js', array('jquery'), null, true );
        wp_localize_script( 'simple-image-hosting-script', 'image_hosting_params', array( 
            'ajax_url' => admin_url( 'admin-ajax.php' ) 
        ));
    }
}
add_action( 'wp_enqueue_scripts', 'simple_image_hosting_assets' );

//8. nonce权限验证
function simple_image_hosting_localize_script() {
    // 获取最大文件大小（以字节为单位）
    $max_file_size = get_option('simple_image_hosting_max_file_size', 2) * 1024 * 1024;
    // 转换为MB
    $max_file_size_mb = round($max_file_size / 1024 / 1024, 2);

    // 获取允许的文件格式
    $allowed_formats = get_option('simple_image_hosting_allowed_formats', 'jpg,jpeg,png');
    $allowed_formats_array = array_map('trim', explode(',', $allowed_formats)); // 转换为数组

    // nonce只有在需要时加载
    if (is_page() && (has_shortcode(get_post()->post_content, 'image_upload_form') || has_shortcode(get_post()->post_content, 'image_manage_page'))) {
        wp_localize_script( 'simple-image-hosting-script', 'image_hosting_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'ajax_nonce' => wp_create_nonce( 'image_upload_nonce' ),
            'max_file_size'  => $max_file_size,
            'allowed_formats' => $allowed_formats_array,
            'max_file_size_mb' => $max_file_size / 1024 / 1024, // 传递给前端的MB单位
        ));
    }
}
add_action( 'wp_enqueue_scripts', 'simple_image_hosting_localize_script' );

//9. 图片上传
function handle_image_upload() {
    // 获取后台设置（默认设置）
    $allowed_formats = get_option( 'simple_image_hosting_allowed_formats', 'jpg,jpeg,png' );
    $allowed_formats = array_map('trim', explode(',', $allowed_formats));
    $max_file_size = get_option( 'simple_image_hosting_max_file_size', 2 ) * 1024 * 1024;

    // 检查文件类型
    if ( isset( $_FILES['files'] ) && ! empty( $_FILES['files']['name'][0] ) ) {
        foreach ( $_FILES['files']['name'] as $key => $file_name ) {
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            // 文件格式检查
            if (!in_array($file_extension, $allowed_formats)) {
                wp_send_json_error( array( 'message' => '不支持此文件类型上传。允许的格式为: ' . implode(', ', $allowed_formats) ) );
            }

            // 文件大小检查
            if ($_FILES['files']['size'][$key] > $max_file_size) {
                wp_send_json_error(array('message' => '文件 "' . $file_name . '" 超过了上传大小限制。最大支持上传 ' . ($max_file_size / 1024 / 1024) . 'MB的文件。'));
            }
        }
    }

    // 检查游客上传权限
    if ( get_option( 'simple_image_hosting_disable_guests' ) && ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => '请先登录才能上传图片' ) );
    }

    // 获取允许上传的用户角色
    $user_roles = get_option( 'simple_image_hosting_user_roles', [] ); // 默认值为空数组
    if (is_string($user_roles)) {
        // 如果是字符串，将其转换为数组
        $user_roles = explode(',', $user_roles);
    }
    $user_roles = (array) $user_roles;

    // 如果用户已登录，进行用户角色权限判断
    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();
        
        // 如果当前用户的角色不在允许上传的角色数组中，就拒绝上传
        if ( ! array_intersect( $user->roles, $user_roles ) ) {
            wp_send_json_error( array( 'message' => '您没有上传图片的权限，请联系管理员开通' ) );
        }
    }

    // 安全验证
    if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'image_upload_nonce' ) ) {
        wp_send_json_error( array( 'message' => '安全验证失败' ) );
    }

    // 检查是否上传了文件
    if ( ! isset( $_FILES['files'] ) || empty( $_FILES['files']['name'][0] ) ) {
        wp_send_json_error( array( 'message' => '没有选择文件' ) );
    }

    $files = $_FILES['files'];
    $uploaded_files = [];

    foreach ( $files['name'] as $key => $file_name ) {
        $file = [
            'name'     => $file_name,
            'type'     => $files['type'][$key],
            'tmp_name' => $files['tmp_name'][$key],
            'error'    => $files['error'][$key],
            'size'     => $files['size'][$key],
        ];

        $upload = wp_handle_upload( $file, array( 'test_form' => false ) );

        if ( isset( $upload['error'] ) ) {
            wp_send_json_error( array( 'message' => $upload['error'] ) );
        }

        $file_path = $upload['file'];
        $file_url  = $upload['url'];
        $file_name = basename($file_url);
        $file_type = wp_check_filetype( basename( $upload['file'] ), null );

        $attachment = array(
            'guid'           => $file_url, 
            'post_mime_type' => $file_type['type'],
            'post_title'     => sanitize_file_name( basename( $file_path ) ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        $attach_id = wp_insert_attachment( $attachment, $file_path );

        if ( ! is_wp_error( $attach_id ) ) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
            wp_update_attachment_metadata( $attach_id, $attach_data );
        }

        $uploaded_files[] = array(
            'url'           => $file_url,  // 图片的URL链接
            'file_name'     => sanitize_file_name($file_name),  // 图片的文件名
            'markdown'      => '!' . '[' . sanitize_file_name($file_name) . '](' . $file_url . ')', // Markdown代码
            'bbcode'        => '[img]' . $file_url . '[/img]', // BBCode代码
            'attachment_id' => $attach_id
        );
    }

    wp_send_json_success( array( 'files' => $uploaded_files ) );
}

add_action( 'wp_ajax_handle_image_upload', 'handle_image_upload' );
add_action( 'wp_ajax_nopriv_handle_image_upload', 'handle_image_upload' );


//10. 前台图片管理页面
function simple_image_hosting_manage_page() {
    // 只有登录用户才能访问
    if ( ! is_user_logged_in() ) {
        wp_redirect( home_url() );
        exit;
    }

    ob_start();

    $current_user = wp_get_current_user();
    $args = array(
        'post_type'   => 'attachment',
        'post_status' => 'inherit',
        'author'      => $current_user->ID,
        'posts_per_page' => -1
    );

    $query = new WP_Query( $args );
    
    if ( $query->have_posts() ) :
        ?>
    <div class="manage-page-simple-image-hosting-manage-container">
        <h2>我的图片管理</h2>
        <div id="upload-message" class="upload-message"></div>
        <div class="manage-page-simple-image-hosting-preview-container">
            <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                <?php $url = wp_get_attachment_url( get_the_ID() ); ?>
                <div class="manage-page-simple-image-hosting-image-item">
                    <img src="<?php echo esc_url( $url ); ?>" class="manage-page-simple-image-hosting-image-preview" />
                    <button class="manage-page-simple-image-hosting-copy-btn" onclick="copyLink('<?php echo esc_js( $url ); ?>')">复制链接</button>
                </div>
            <?php endwhile; ?>
        </div>
        </div>
        <?php
        wp_reset_postdata();
    else :
        echo '<p>您还没有上传任何图片。</p>';
    endif;

    return ob_get_clean();
}

add_shortcode( 'image_manage_page', 'simple_image_hosting_manage_page' );


//11. 获取图片管理页面的链接
function get_image_manage_page_url() {
    // 获取所有已发布的页面
    $pages = get_pages([
        'post_status' => 'publish'
    ]);

    // 遍历页面，检查内容中是否包含[image_manage_page]短代码
    foreach ($pages as $page) {
        if (has_shortcode($page->post_content, 'image_manage_page')) {
            // 返回包含短代码的页面的URL
            return get_permalink($page->ID);
        }
    }

    // 如果没有找到包含短代码的页面，返回null
    return null;
}


//12. 后台查询页面
function simple_image_hosting_user_images_page() {
    // 管理员才可以访问此页面
    if (!current_user_can('manage_options')) {
        wp_die('抱歉，您不能访问此页面。');
    }

    $per_page = 20; // 每页显示的图片数
    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    
    ?>
    <div class="wrap">
        <h1>查询用户上传的图片</h1>
        <form method="get" action="">
            <input type="hidden" name="page" value="simple-image-hosting-user-images" />
            <label for="user_id">输入用户ID：</label>
            <input type="number" name="user_id" id="user_id" value="<?php echo isset($_GET['user_id']) ? esc_attr($_GET['user_id']) : ''; ?>" />
            <input type="submit" value="查询" class="button-primary" />
        </form>

        <?php
        if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
            $user_id = intval($_GET['user_id']);
            
            // 获取该用户上传的图片数量
            $args_count = array(
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'author' => $user_id,
                'posts_per_page' => -1 // 获取所有图片
            );
            $query_count = new WP_Query($args_count);
            $total_images = $query_count->found_posts; // 总图片数量
            wp_reset_postdata();
            
            // 获取分页内容
            $args = array(
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'author' => $user_id,
                'posts_per_page' => $per_page,
                'paged' => $paged
            );

            $query = new WP_Query($args);

            if ($query->have_posts()) :
                ?>
                <h2>用户ID：<?php echo esc_html($user_id); ?> 上传的图片</h2>
                <table class="wp-list-table widefat fixed striped posts">
                    <thead>
                        <tr>
                            <th>图片预览</th>
                            <th>图片链接</th>
                            <th>删除</th>
                        </tr>
                    </thead>
                    <tbody id="image-table-body">
                        <?php while ($query->have_posts()) : $query->the_post(); 
                            $image_url = wp_get_attachment_url(get_the_ID()); 
                            ?>
                            <tr id="image-<?php echo get_the_ID(); ?>">
                                <td><img src="<?php echo esc_url($image_url); ?>" width="100" /></td>
                                <td><input type="text" value="<?php echo esc_url($image_url); ?>" readonly /></td>
                                <td>
                                    <button class="delete-image button-primary" data-image-id="<?php echo get_the_ID(); ?>">删除</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <?php
                // 显示分页按钮
                $total_pages = ceil($total_images / $per_page);
                if ($total_pages > 1) :
                    ?>
                    <div class="pagination">
                        <?php
                        // 生成分页链接
                        for ($i = 1; $i <= $total_pages; $i++) {
                            $class = ($paged == $i) ? ' class="current"' : '';
                            echo '<a href="' . add_query_arg(array('paged' => $i)) . '"'.$class.'>' . $i . '</a>';
                        }
                        ?>
                    </div>
                <?php
                endif;

                wp_reset_postdata();
            else :
                echo '<p>该用户没有上传任何图片。</p>';
            endif;
        }
        ?>

        <!-- 删除所有图片按钮 -->
        <?php if (isset($user_id)) : ?>
        <form method="post">
            <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>" />
            <button type="button" class="delete-all-images button-secondary" data-user-id="<?php echo esc_attr($user_id); ?>">
                删除该用户所有图片
            </button>
        </form>
        <?php endif; ?>
    </div>

<script type="text/javascript">
    // AJAX 删除单张图片
    jQuery(document).ready(function($) {
        // 删除单个图片
        $('.delete-image').click(function(e) {
            e.preventDefault(); // 防止触发默认的按钮行为
            var imageId = $(this).data('image-id');

            // 弹出确认框
            if (confirm('确认删除这张图片吗？')) {
                var row = $('#image-' + imageId); // 获取要删除的图片所在的行
                $.post(ajaxurl, {
                    action: 'delete_user_image',
                    image_id: imageId
                }, function(response) {
                    if(response.success) {
                        row.remove(); // 删除图片所在的行
                        showMessage('图片删除成功！');
                    } else {
                        alert('删除失败，请稍后再试');
                    }
                });
            }
        });

        // 删除该用户所有图片
        $('.delete-all-images').click(function(e) {
            e.preventDefault(); // 防止表单提交

            var userId = $(this).data('user-id');

            // 弹出确认框
            if (confirm('确认删除该用户的所有图片吗？')) {
                var form = $(this).closest('form'); // 获取表单元素

                // 发起 AJAX 请求
                $.post(ajaxurl, {
                    action: 'delete_all_user_images',
                    user_id: userId
                }, function(response) {
                    if(response.success) {
                        showMessage('该用户的所有图片已删除');
                        // 清除当前页面的图片列表
                        location.reload();
                    } else {
                        alert('删除失败，请稍后再试');
                    }
                });
            }
        });

        // 显示消息
        function showMessage(message) {
            var messageBox = $('<div class="updated fade" style="margin-top: 10px;">' + message + '</div>');
            $('.wrap').prepend(messageBox); // 在页面顶部显示消息

            // 1秒后自动消失
            setTimeout(function() {
                messageBox.fadeOut('slow', function() {
                    $(this).remove();
                });
            }, 1000);
        }
    });
</script>


    <?php
}

//13. 单个图片删除
function simple_image_hosting_delete_user_image_ajax() {
    if (isset($_POST['image_id'])) {
        // 验证删除图片权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '无权操作'));
            return;
        }

        $image_id = intval($_POST['image_id']);
        // 删除附件
        wp_delete_attachment($image_id, true);

        // 返回成功消息
        wp_send_json_success();
    } else {
        wp_send_json_error(array('message' => '无效的图片ID'));
    }
}
add_action('wp_ajax_delete_user_image', 'simple_image_hosting_delete_user_image_ajax');

//14. 删除该用户所有图片
function simple_image_hosting_delete_all_user_images() {
    if (isset($_POST['action']) && $_POST['action'] == 'delete_all_user_images' && isset($_POST['user_id'])) {
        // 验证删除图片权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error(); // 权限不足，返回错误
        }

        $user_id = intval($_POST['user_id']);
        // 获取该用户所有上传的图片
        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'author' => $user_id,
            'posts_per_page' => -1
        );

        $query = new WP_Query($args);
        while ($query->have_posts()) : $query->the_post();
            wp_delete_attachment(get_the_ID(), true); // 删除图片
        endwhile;

        wp_reset_postdata();

        // 返回成功响应
        wp_send_json_success();
    }
}
add_action('wp_ajax_delete_all_user_images', 'simple_image_hosting_delete_all_user_images');

//15. 查询用户列表分页样式
function add_pagination_styles() {
    echo '
    <style>
        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
        }
        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 4px;
            background-color: #f1f1f1;
            color: #0073aa;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .pagination a.current {
            background-color: #0073aa;
            color: white;
        }
        .pagination a:hover {
            background-color: #0073aa;
            color: white;
        }
    </style>
    ';
}
add_action('admin_head', 'add_pagination_styles');
?>
