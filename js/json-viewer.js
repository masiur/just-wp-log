(function($) {
    'use strict';
    
    function isJsonString(str) {
        try {
            JSON.parse(str);
            return true;
        } catch (e) {
            return false;
        }
    }
    
    function createToggleButton() {
        return $('<button>', {
            class: 'json-toggle',
            text: '▶',
            click: function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $this = $(this);
                const $content = $this.siblings('.json-content');
                
                if ($content.is(':visible')) {
                    $content.hide();
                    $this.text('▶');
                } else {
                    $content.show();
                    $this.text('▼');
                }
            }
        });
    }
    
    function formatJson(json) {
        if (typeof json === 'string') {
            if (!isJsonString(json)) {
                return $('<span>').text(json);
            }
            json = JSON.parse(json);
        }
        
        if (Array.isArray(json)) {
            const $container = $('<div>', { class: 'json-array' });
            const $toggle = createToggleButton();
            const $content = $('<div>', { class: 'json-content' }).hide();
            
            $container.append($toggle, ' [');
            json.forEach((item, index) => {
                const $item = formatJson(item);
                $content.append($item);
                if (index < json.length - 1) {
                    $content.append(',');
                }
                $content.append('<br>');
            });
            $container.append($content);
            $container.append(']');
            
            return $container;
        } else if (typeof json === 'object' && json !== null) {
            const $container = $('<div>', { class: 'json-object' });
            const $toggle = createToggleButton();
            const $content = $('<div>', { class: 'json-content' }).hide();
            
            $container.append($toggle, ' {');
            const keys = Object.keys(json);
            keys.forEach((key, index) => {
                const $key = $('<span>', { class: 'json-key', text: '"' + key + '": ' });
                const $value = formatJson(json[key]);
                $content.append($key, $value);
                if (index < keys.length - 1) {
                    $content.append(',');
                }
                $content.append('<br>');
            });
            $container.append($content);
            $container.append('}');
            
            return $container;
        } else {
            return $('<span>').text(JSON.stringify(json));
        }
    }
    
    window.formatLogData = function(data) {
        if (!data || typeof data !== 'string') {
            return data;
        }
        
        if (isJsonString(data)) {
            const $container = $('<div>', { class: 'json-viewer' });
            $container.append(formatJson(data));
            return $container;
        }
        
        return data;
    };
})(jQuery);