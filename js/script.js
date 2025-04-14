(function($) {
    'use strict';
    
    let currentPage = 1;
    let currentSearch = '';
    
    // Add the missing formatLogData function
    window.formatLogData = function(text) {
        try {
            // Attempt to pretty print JSON if possible
            // Detect if content is valid JSON
            JSON.parse(text);
            // If it reaches here, it's valid JSON, so syntax highlight it
            return '<code class="jhl-json-highlight">' + 
                   text.replace(/&/g, '&amp;')
                       .replace(/<//g, '&lt;')
                       .replace(/>/g, '&gt;')
                       .replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                            let cls = 'jhl-json-number';
                            if (/^"/.test(match)) {
                                if (/:$/.test(match)) {
                                    cls = 'jhl-json-key';
                                } else {
                                    cls = 'jhl-json-string';
                                }
                            } else if (/true|false/.test(match)) {
                                cls = 'jhl-json-boolean';
                            } else if (/null/.test(match)) {
                                cls = 'jhl-json-null';
                            }
                            return '<span class="' + cls + '">' + match + '</span>';
                       }) + 
                   '</code>';
        } catch (e) {
            // Not valid JSON, just escape and return as plain text
            return text.replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;');
        }
    };

    // Update URL query parameters without page reload
    function updateUrlParams(page, search) {
        const url = new URL(window.location.href);
        const currentParams = new URLSearchParams(url.search);
        
        // Maintain the plugin page parameter
        currentParams.set('page', 'just-log-viewer');
        
        if (page && page > 1) {
            currentParams.set('jhlpage', page);
        } else {
            currentParams.delete('jhlpage');
        }
        
        if (search && search.trim() !== '') {
            currentParams.set('jhlsearch', search);
        } else {
            currentParams.delete('jhlsearch');
        }
        
        url.search = currentParams.toString();
        window.history.pushState({page, search}, '', url);
    }

    // Parse URL parameters on load
    function getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        const pageParam = params.get('jhlpage');
        const searchParam = params.get('jhlsearch');
        
        return {
            page: pageParam ? parseInt(pageParam) : 1,
            search: searchParam || ''
        };
    }
    
    function loadLogs(page = null, search = null) {
        // If parameters are null, try to get from URL
        if (page === null || search === null) {
            const params = getUrlParams();
            if (page === null) page = params.page;
            if (search === null) search = params.search;
        }
        
        currentPage = page;
        currentSearch = search;
        
        // Update search box value
        $('#jhl-log-search').val(search);
        
        // Update URL without page reload
        updateUrlParams(page, search);
        
        $('#jhl-log-container').html('<div class="jhl-spinner-container"><span class="spinner is-active"></span></div>');
        
        $.ajax({
            url: justLogData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'just_log_search',
                nonce: justLogData.nonce,
                page: page,
                search: search,
                per_page: justLogData.perPage
            },
            success: function(response) {
                if (response.success) {
                    $('#jhl-log-container').html(response.data.html);
                    
                    // Convert UTC timestamps to local time
                    convertTimestampsToLocal();
                    
                    // Re-attach event listeners to the newly loaded pagination links
                    attachPaginationListeners();
                } else {
                    $('#jhl-log-container').html(
                        '<div class="notice notice-error"><p>' + 
                        (response.data.message || 'Error loading logs') + 
                        '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                $('#jhl-log-container').html(
                    '<div class="notice notice-error"><p>Error loading logs: ' + 
                    (error || 'Unknown error') + 
                    '</p></div>'
                );
            }
        });
    }
    
    // Convert UTC timestamps to local browser time
    function convertTimestampsToLocal() {
        $('.jhl-log-time-local').each(function() {
            const utcTimestamp = parseInt($(this).data('timestamp'));
            if (!isNaN(utcTimestamp)) {
                const date = new Date(utcTimestamp * 1000);
                const localTimeStr = formatLocalTime(date);
                $(this).html('Local: ' + localTimeStr);
            }
        });
    }
    
    // Format local time
    function formatLocalTime(date) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        const month = months[date.getMonth()];
        const day = date.getDate();
        const year = date.getFullYear();
        
        let hours = date.getHours();
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // Convert hour '0' to '12'
        
        return `${month} ${day}, ${year} ${hours}:${minutes}:${seconds} ${ampm}`;
    }
    
    function renderPagination(currentPage, totalPages) {
        const paginationContainer = document.querySelector('.jhl-pagination-container');
        const pageInfoContainer = document.querySelector('.jhl-page-info');
        const pageInput = document.getElementById('jhl-page-input');
        
        if (!paginationContainer) return;
        
        // Update page input and info
        if (pageInput) {
            pageInput.setAttribute('max', totalPages);
            pageInput.setAttribute('placeholder', `1-${totalPages}`);
            pageInput.value = ''; // Reset input
        }
        
        if (pageInfoContainer) {
            pageInfoContainer.innerHTML = `Page ${currentPage} of ${totalPages}`;
        }
        
        let paginationHTML = '';
        
        // Previous button
        if (currentPage > 1) {
            paginationHTML += `<a href="javascript:void(0);" class="jhl-page-numbers prev" data-page="${currentPage - 1}">« Prev</a>`;
        } else {
            paginationHTML += `<span class="jhl-page-numbers prev disabled">« Prev</span>`;
        }
        
        // First page always visible
        const firstPageClass = currentPage === 1 ? 'current' : '';
        if (currentPage === 1) {
            paginationHTML += `<span class="jhl-page-numbers ${firstPageClass}">${1}</span>`;
        } else {
            paginationHTML += `<a href="javascript:void(0);" class="jhl-page-numbers" data-page="1">1</a>`;
        }
        
        // Ellipsis after first page
        if (currentPage > 4) {
            paginationHTML += `<span class="jhl-page-numbers dots">…</span>`;
        }
        
        // Pages around current page
        const startPage = Math.max(2, currentPage - 2);
        const endPage = Math.min(totalPages - 1, currentPage + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            if (i === 1 || i === totalPages) continue; // Skip first and last pages as they're always shown
            
            if (i === currentPage) {
                paginationHTML += `<span class="jhl-page-numbers current">${i}</span>`;
            } else {
                paginationHTML += `<a href="javascript:void(0);" class="jhl-page-numbers" data-page="${i}">${i}</a>`;
            }
        }
        
        // Ellipsis before last page
        if (currentPage < totalPages - 3) {
            paginationHTML += `<span class="jhl-page-numbers dots">…</span>`;
        }
        
        // Last page always visible if more than one page
        if (totalPages > 1) {
            const lastPageClass = currentPage === totalPages ? 'current' : '';
            if (currentPage === totalPages) {
                paginationHTML += `<span class="jhl-page-numbers ${lastPageClass}">${totalPages}</span>`;
            } else {
                paginationHTML += `<a href="javascript:void(0);" class="jhl-page-numbers" data-page="${totalPages}">${totalPages}</a>`;
            }
        }
        
        // Next button
        if (currentPage < totalPages) {
            paginationHTML += `<a href="javascript:void(0);" class="jhl-page-numbers next" data-page="${currentPage + 1}">Next »</a>`;
        } else {
            paginationHTML += `<span class="jhl-page-numbers next disabled">Next »</span>`;
        }
        
        paginationContainer.innerHTML = paginationHTML;
        
        // Add event listeners to pagination links
        $('.jhl-pagination-container .jhl-page-numbers').off('click').on('click', function(e) {
            if ($(this).hasClass('disabled') || $(this).hasClass('dots') || $(this).hasClass('current')) {
                e.preventDefault();
                return false;
            }
            
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (!isNaN(page)) {
                loadLogs(page, currentSearch);
            }
            return false;
        });
    }
    
    // Function to attach event listeners to pagination links
    function attachPaginationListeners() {
        $('.jhl-pagination-container .jhl-page-numbers:not(.disabled):not(.dots):not(.current)').on('click', function(e) {
            e.preventDefault();
            const page = parseInt($(this).attr('data-page'));
            loadLogs(page, currentSearch);
        });
    }
    
    // Handle browser back/forward buttons
    $(window).on('popstate', function(event) {
        if (event.originalEvent.state) {
            loadLogs(event.originalEvent.state.page || 1, event.originalEvent.state.search || '');
        } else {
            const params = getUrlParams();
            loadLogs(params.page, params.search);
        }
    });
    
    $(document).ready(function() {
        // Load logs based on URL parameters
        const urlParams = getUrlParams();
        loadLogs(urlParams.page, urlParams.search);
        
        // Basic scroll functionality using buttons in the HTML
        $('#jhl-scroll-top, #jhl-floating-scroll-top').on('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        $('#jhl-scroll-bottom, #jhl-floating-scroll-bottom').on('click', function() {
            window.scrollTo({
                top: document.body.scrollHeight,
                behavior: 'smooth'
            });
        });
        
        // Show/hide floating scroll buttons based on scroll position
        $(window).on('scroll', function() {
            const scrollTop = $(window).scrollTop();
            
            // Show floating buttons when scrolled down a bit
            if (scrollTop > 300) {
                $('.jhl-floating-scroll').css('display', 'flex');
            } else {
                $('.jhl-floating-scroll').css('display', 'none');
            }
        });
        
        // Initialize button visibility
        $(window).trigger('scroll');
        
        // Search button click
        $('#jhl-search-logs').on('click', function() {
            const searchTerm = $('#jhl-log-search').val();
            loadLogs(1, searchTerm);
        });
        
        // Enter key in search box
        $('#jhl-log-search').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                const searchTerm = $(this).val();
                loadLogs(1, searchTerm);
            }
        });
        
        // Reset search
        $('#jhl-reset-search').on('click', function() {
            $('#jhl-log-search').val('');
            loadLogs(1, '');
        });
        
        // Refresh logs
        $('#jhl-refresh-logs').on('click', function() {
            loadLogs(currentPage, currentSearch);
        });
        
        // Clear logs
        $('#jhl-clear-logs').on('click', function() {
            if (!confirm('Are you sure you want to delete all logs?')) {
                return;
            }
            
            $.ajax({
                url: justLogData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'just_log_clear',
                    nonce: justLogData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        loadLogs(1, '');
                        $('#jhl-log-search').val('');
                    } else {
                        alert('Failed to clear logs');
                    }
                },
                error: function() {
                    alert('Error clearing logs');
                }
            });
        });
        
        // Go to page functionality
        $(document).on('click', '#jhl-go-to-page', function() {
            const pageInput = document.getElementById('jhl-page-input');
            if (pageInput) {
                const pageValue = parseInt(pageInput.value);
                if (!isNaN(pageValue) && pageValue > 0) {
                    loadLogs(pageValue, currentSearch);
                } else {
                    alert('Please enter a valid page number');
                }
            }
        });
        
        $(document).on('keypress', '#jhl-page-input', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $('#jhl-go-to-page').click();
            }
        });
    });
})(jQuery);