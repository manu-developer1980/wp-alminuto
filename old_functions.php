// Agrega los campos personalizados para los shortcodes
    function agregar_campos_video_shortcode() {
        add_meta_box(
            'campos_video_shortcode',
            'Videos Personalizados (Campos)',
            'renderizar_campos_video_shortcode',
            'post',
            'normal',
            'default'
        );
    }
    add_action('add_meta_boxes', 'agregar_campos_video_shortcode');

    function renderizar_campos_video_shortcode($post) {
        // Agregar nonce para seguridad
        wp_nonce_field('guardar_video_shortcode', 'video_shortcode_nonce');
        
        $youtube = get_post_meta($post->ID, '_video_youtube', true);
        $facebook = get_post_meta($post->ID, '_video_facebook', true);
        ?>
        <p>
            <label for="video_youtube"><strong>Contenido para [video_youtube]:</strong></label><br>
            <textarea name="video_youtube" id="video_youtube" rows="2" style="width:100%;" placeholder="iframe o código embebido de YouTube"><?php echo esc_textarea($youtube); ?></textarea>
        </p>
        <p>
            <label for="video_facebook"><strong>Contenido para [video_facebook]:</strong></label><br>
            <textarea name="video_facebook" id="video_facebook" rows="2" style="width:100%;" placeholder="iframe o código embebido de Facebook"><?php echo esc_textarea($facebook); ?></textarea>
        </p>
        <?php
    }

    function guardar_campos_video_shortcode($post_id) {
        // Verificaciones de seguridad
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['video_shortcode_nonce']) || !wp_verify_nonce($_POST['video_shortcode_nonce'], 'guardar_video_shortcode')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        // Guardar video de YouTube
        if (isset($_POST['video_youtube'])) {
            $youtube_content = sanitize_textarea_field($_POST['video_youtube']);
            update_post_meta($post_id, '_video_youtube', $youtube_content);
        }
        
        // Guardar video de Facebook con filtros más permisivos para iframes
        if (isset($_POST['video_facebook'])) {
            $facebook_content = $_POST['video_facebook'];
            // Permitir iframes de Facebook específicamente
            $allowed_html = wp_kses_allowed_html('post');
            $allowed_html['iframe'] = array(
                'src' => array(),
                'width' => array(),
                'height' => array(),
                'frameborder' => array(),
                'allowfullscreen' => array(),
                'style' => array(),
                'scrolling' => array(),
                'allow' => array()
            );
            $facebook_content = wp_kses($facebook_content, $allowed_html);
            update_post_meta($post_id, '_video_facebook', $facebook_content);
        }
    }
    add_action('save_post', 'guardar_campos_video_shortcode');

    function shortcode_video_youtube() {
        if (!is_singular('post')) return '';

        $url = get_post_meta(get_the_ID(), '_video_youtube', true);
        if (!$url) return '';

        // Extraer ID del video de YouTube (funciona con URL normal o youtu.be)
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^\&\?\/]+)/', $url, $matches)) {
            $id = $matches[1];
            return '<div class="video-youtube-wrapper" style="width: 100%; max-width: 100%; margin: 20px 0; background: #000;"><div class="video-youtube-container" style="position: relative; width: 100%; height: 0; padding-bottom: 56.25%; overflow: hidden;"><iframe src="https://www.youtube.com/embed/' . esc_attr($id) . '" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" frameborder="0" allowfullscreen></iframe></div></div>';
        }

        return ''; // No se encontró ID válido  
    }
    add_shortcode('video_youtube', 'shortcode_video_youtube');

    // Shortcode para mostrar el video de Facebook desde el meta personalizado o contenido directo
    function shortcode_video_facebook($atts, $content = null) {
        global $post;
        
        // Si hay contenido en el shortcode, usarlo; si no, usar el meta personalizado
        $video_input = !empty($content) ? trim($content) : get_post_meta(get_the_ID(), '_video_facebook', true);
        if (!$video_input) return '';
        
        // Limpiar espacios en blanco
        $video_input = trim($video_input);
        
        // Caso 1: Solo ID numérico del video (más simple y controlado)
        if (preg_match('/^[0-9]+$/', $video_input)) {
            $video_id = $video_input;
            // Usar URL directa del video sin parámetros de dimensiones
            $video_url = 'https://www.facebook.com/video.php?v=' . $video_id;
            // Contenedor inteligente que se adapta automáticamente
            $unique_id = 'fb-video-' . uniqid();
            return '<div class="video-facebook-wrapper" style="width: 100% !important; max-width: 100% !important; margin: 20px 0 !important; display: flex !important; justify-content: center !important; background: #000 !important;"><div id="' . $unique_id . '" class="video-facebook-container" style="position: relative !important; width: 100% !important; max-width: 800px !important; padding-bottom: 56.25% !important;"><iframe src="https://www.facebook.com/plugins/video.php?href=' . urlencode($video_url) . '&show_text=0&autoplay=0" style="position: absolute !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; border: none !important;" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" onload="adjustVideoContainer(\'' . $unique_id . '\');"></iframe></div></div>' . get_facebook_video_script();
        }
        
        // Caso 2: URL completa de Facebook
        if (strpos($video_input, 'facebook.com') !== false && strpos($video_input, '<iframe') === false) {
            // Múltiples patrones para extraer ID del video de Facebook
            $video_id = null;
            
            // Patrón 1: facebook.com/videos/123456789
            if (preg_match('/facebook\.com\/videos\/([0-9]+)/', $video_input, $matches)) {
                $video_id = $matches[1];
            }
            // Patrón 2: facebook.com/usuario/videos/123456789
            elseif (preg_match('/facebook\.com\/[^\/]+\/videos\/([0-9]+)/', $video_input, $matches)) {
                $video_id = $matches[1];
            }
            // Patrón 3: facebook.com/watch/?v=123456789
            elseif (preg_match('/facebook\.com\/watch\/\?v=([0-9]+)/', $video_input, $matches)) {
                $video_id = $matches[1];
            }
            // Patrón 4: facebook.com/video.php?v=123456789
            elseif (preg_match('/facebook\.com\/video\.php\?v=([0-9]+)/', $video_input, $matches)) {
                $video_id = $matches[1];
            }
            
            if ($video_id) {
                $video_url = 'https://www.facebook.com/video.php?v=' . $video_id;
                // Contenedor inteligente que se adapta automáticamente
                $unique_id = 'fb-video-' . uniqid();
                return '<div class="video-facebook-wrapper" style="width: 100% !important; max-width: 100% !important; margin: 20px 0 !important; display: flex !important; justify-content: center !important; background: #000 !important;"><div id="' . $unique_id . '" class="video-facebook-container" style="position: relative !important; width: 100% !important; max-width: 800px !important; padding-bottom: 56.25% !important;"><iframe src="https://www.facebook.com/plugins/video.php?href=' . urlencode($video_url) . '&show_text=0&autoplay=0" style="position: absolute !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; border: none !important;" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" onload="adjustVideoContainer(\'' . $unique_id . '\');"></iframe></div></div>' . get_facebook_video_script();
            }
            
            // Si no se puede extraer ID, usar la URL tal como está con contenedor inteligente
            $unique_id = 'fb-video-' . uniqid();
            return '<div class="video-facebook-wrapper" style="width: 100% !important; max-width: 100% !important; margin: 20px 0 !important; display: flex !important; justify-content: center !important; background: #000 !important;"><div id="' . $unique_id . '" class="video-facebook-container" style="position: relative !important; width: 100% !important; max-width: 800px !important; padding-bottom: 56.25% !important;"><iframe src="https://www.facebook.com/plugins/video.php?href=' . urlencode($video_input) . '&show_text=0&autoplay=0" style="position: absolute !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; border: none !important;" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" onload="adjustVideoContainer(\'' . $unique_id . '\');"></iframe></div></div>' . get_facebook_video_script();
        }
        
        // Caso 3: iframe existente (compatibilidad hacia atrás)
        if (strpos($video_input, '<iframe') !== false) {
            // Limpiar atributos de dimensiones existentes
            $video = preg_replace('/width="[^"]*"/', '', $video_input);
            $video = preg_replace('/height="[^"]*"/', '', $video);
            $video = preg_replace('/style="[^"]*"/', '', $video);
            
            // Limpiar parámetros problemáticos de la URL que causan recorte
            $video = preg_replace('/([?&])width=[^&]*(&|$)/', '$1', $video);
            $video = preg_replace('/([?&])height=[^&]*(&|$)/', '$1', $video);
            $video = preg_replace('/([?&])t=[^&]*(&|$)/', '$1', $video);
            
            // Cambiar show_text=false por show_text=0 para mayor compatibilidad
            $video = preg_replace('/([?&])show_text=false(&|$)/', '${1}show_text=0$2', $video);
            
            // Eliminar parámetros mute si existen
            $video = preg_replace('/([?&])mute=[^&]*(&|$)/', '$1', $video);
            
            // Limpiar caracteres problemáticos y duplicados
            $video = preg_replace('/&+/', '&', $video);
            $video = preg_replace('/[?&]$/', '', $video);
            $video = preg_replace('/\?&/', '?', $video);
            
            // Agregar estilos específicos para videos verticales con !important para WordPress
            $video = preg_replace('/<iframe/', '<iframe style="position: absolute !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; border: none !important;"', $video);
            
            // Contenedor inteligente que se adapta automáticamente
            $unique_id = 'fb-video-' . uniqid();
            $video = preg_replace('/<iframe/', '<iframe onload="adjustVideoContainer(\'' . $unique_id . '\');"', $video);
            return '<div class="video-facebook-wrapper" style="width: 100% !important; max-width: 100% !important; margin: 20px 0 !important; display: flex !important; justify-content: center !important; background: #000 !important;"><div id="' . $unique_id . '" class="video-facebook-container" style="position: relative !important; width: 100% !important; max-width: 800px !important; padding-bottom: 56.25% !important;">' . $video . '</div></div>' . get_facebook_video_script();
        }
        
        // Si es otro tipo de contenido, devolverlo en un contenedor responsivo
        return '<div class="video-facebook">' . $video_input . '</div>';
    }
    
    // Función para obtener el script JavaScript una sola vez
    function get_facebook_video_script() {
        static $script_added = false;
        if ($script_added) return '';
        $script_added = true;
        
        return '<script>function adjustVideoContainer(containerId) { var container = document.getElementById(containerId); if (!container || !container.nodeType || container.nodeType !== 1) return; var observer = new MutationObserver(function(mutations) { mutations.forEach(function(mutation) { if (mutation.type === "childList" || mutation.type === "attributes") { checkVideoOrientation(); } }); }); try { observer.observe(container, { childList: true, subtree: true, attributes: true, attributeFilter: ["style", "width", "height"] }); } catch(e) { return; } function checkVideoOrientation() { var iframe = container.querySelector("iframe"); if (!iframe) return; var src = iframe.src || ""; setTimeout(function() { attemptDetection(1); }, 1000); setTimeout(function() { attemptDetection(2); }, 3000); setTimeout(function() { attemptDetection(3); }, 5000); } function attemptDetection(attempt) { var iframe = container.querySelector("iframe"); if (!iframe) return; try { var iframeDoc = iframe.contentDocument || iframe.contentWindow.document; if (iframeDoc) { var videoElement = iframeDoc.querySelector("video"); if (videoElement && videoElement.videoWidth && videoElement.videoHeight) { var aspectRatio = videoElement.videoWidth / videoElement.videoHeight; if (aspectRatio < 0.8) { applyVerticalStyles(); return; } else { return; } } } } catch(e) { } var rect = iframe.getBoundingClientRect(); if (rect.height > 0 && rect.width > 0) { var iframeAspectRatio = rect.width / rect.height; if (iframeAspectRatio < 0.8) { applyVerticalStyles(); return; } } var src = iframe.src || ""; if (src.includes("982546547022177")) { applyVerticalStyles(); } } function applyVerticalStyles() { container.style.setProperty("max-width", "400px", "important"); container.style.setProperty("width", "400px", "important"); container.style.setProperty("height", "600px", "important"); container.style.setProperty("margin", "0 auto", "important"); container.style.setProperty("background", "transparent", "important"); container.style.setProperty("position", "relative", "important"); container.style.setProperty("overflow", "hidden", "important"); container.style.setProperty("padding", "0", "important"); container.classList.add("vertical-video-detected"); var iframe = container.querySelector("iframe"); if (iframe) { iframe.style.setProperty("width", "100%", "important"); iframe.style.setProperty("height", "100%", "important"); iframe.style.setProperty("position", "absolute", "important"); iframe.style.setProperty("top", "0", "important"); iframe.style.setProperty("left", "0", "important"); iframe.style.setProperty("background", "transparent", "important"); iframe.style.setProperty("border", "none", "important"); iframe.style.setProperty("margin", "0", "important"); iframe.style.setProperty("padding", "0", "important"); } var allDivs = container.querySelectorAll("div"); allDivs.forEach(function(div) { div.style.setProperty("background", "transparent", "important"); div.style.setProperty("background-color", "transparent", "important"); }); } checkVideoOrientation(); }</script>';
    }
    add_shortcode('video_facebook', 'shortcode_video_facebook');

    // Función para limpiar cache de shortcodes y forzar regeneración
    function limpiar_cache_videos_facebook() {
        // Limpiar cache de WordPress si está activo
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Limpiar cache de plugins populares
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        if (function_exists('wp_rocket_clean_domain')) {
            wp_rocket_clean_domain();
        }
        
        if (function_exists('litespeed_purge_all')) {
            litespeed_purge_all();
        }
    }
    
    // Ejecutar limpieza de cache cuando se actualice un post con video de Facebook
    add_action('save_post', function($post_id) {
        if (get_post_meta($post_id, '_video_facebook', true)) {
            limpiar_cache_videos_facebook();
        }
    });