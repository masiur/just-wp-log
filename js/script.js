(function($) {
    'use strict';
    
    let currentPage = 1;
    let currentSearch = '';
    
    function loadLogs(page = 1, search = '') {
        currentPage = page;
        currentSearch = search;
        
        $('#log-container').addClass('loading');
        
        fetch(justLogData.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'just_log_search',
                nonce: justLogData.nonce,
                page: page,
                search: search,
                per_page: justLogData.perPage
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const $logContent = $(data.data.html);
                
                // Format log data entries
                $logContent.find('.log-data').each(function() {
                    const $this = $(this);
                    const formattedData = window.formatLogData($this.text());
                    $this.html(formattedData);
                });
                
                $('#log-container').html($logContent);
                
                // Set up pagination click handlers
                $('#log-container .page-numbers').on('click', function(e) {
                    e.preventDefault();
                    
                    if ($(this).hasClass('current')) {
                        return;
                    }
                    
                    let page = $(this).text();
                    
                    if ($(this).hasClass('prev')) {
                        page = currentPage - 1;
                    } else if ($(this).hasClass('next')) {
                        page = currentPage + 1;
                    }
                    
                    loadLogs(parseInt(page), currentSearch);
                });
            } else {
                $('#log-container').html('<div class="notice notice-error"><p>Error loading logs</p></div>');
            }
            
            $('#log-container').removeClass('loading');
        })
        .catch(error => {
            console.error('Error:', error);
            $('#log-container').html('<div class="notice notice-error"><p>Error loading logs</p></div>');
            $('#log-container').removeClass('loading');
        });
    }
    
    function clearLogs() {
        if (!confirm('Are you sure you want to delete all logs?')) {
            return;
        }
        
        fetch(justLogData.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'just_log_clear',
                nonce: justLogData.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadLogs(1, '');
                $('#log-search').val('');
            } else {
                alert('Failed to clear logs');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error clearing logs');
        });
    }
    
    // Handle floating scroll button visibility
    function handleScrollButtonVisibility() {
        const scrollTop = $(window).scrollTop();
        const windowHeight = $(window).height();
        const documentHeight = $(document).height();
        
        // Show floating buttons when scrolled down a bit
        if (scrollTop > 300) {
            $('.floating-scroll').css('display', 'flex');
        } else {
            $('.floating-scroll').css('display', 'none');
        }
    }
    
    $(document).ready(function() {
        // Initial load
        loadLogs();
        
        // Basic scroll functionality using buttons in the HTML
        $('#scroll-top, #floating-scroll-top').on('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        $('#scroll-bottom, #floating-scroll-bottom').on('click', function() {
            window.scrollTo({
                top: document.body.scrollHeight,
                behavior: 'smooth'
            });
        });
        
        // Show/hide floating scroll buttons based on scroll position
        $(window).on('scroll', handleScrollButtonVisibility);
        
        // Initialize button visibility
        handleScrollButtonVisibility();
        
        // Search button click
        $('#search-logs').on('click', function() {
            const searchTerm = $('#log-search').val();
            loadLogs(1, searchTerm);
        });
        
        // Enter key in search box
        $('#log-search').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                const searchTerm = $(this).val();
                loadLogs(1, searchTerm);
            }
        });
        
        // Reset search
        $('#reset-search').on('click', function() {
            $('#log-search').val('');
            loadLogs(1, '');
        });
        
        // Refresh logs
        $('#refresh-logs').on('click', function() {
            loadLogs(currentPage, currentSearch);
        });
        
        // Clear logs
        $('#clear-logs').on('click', clearLogs);
    });
})(jQuery);