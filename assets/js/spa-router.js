/**
 * SPA Router for EduScore
 * Handles dynamic page loading without full page reloads
 */

class SPARouter {
    constructor() {
        this.cache = new Map(); // Cache loaded pages
        this.currentUrl = window.location.pathname;
        this.mainContent = document.querySelector('.main-content');
        this.loadingIndicator = null;
        this.init();
    }

    init() {
        // Intercept all sidebar link clicks
        this.interceptNavigation();
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', (event) => {
            const url = window.location.pathname;
            this.loadContent(url, false);
        });
        
        // Initial page load
        const initialUrl = window.location.pathname;
        if (initialUrl !== '/' && initialUrl !== '/dashboard') {
            this.loadContent(initialUrl, false);
        }
        
        // Setup loading indicator
        this.createLoadingIndicator();
    }

    createLoadingIndicator() {
        this.loadingIndicator = document.createElement('div');
        this.loadingIndicator.className = 'spa-loading-indicator';
        this.loadingIndicator.innerHTML = `
            <div class="spa-loader">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Loading...</span>
            </div>
        `;
        this.loadingIndicator.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255,255,255,0.95);
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 9999;
            display: none;
            font-weight: 600;
            color: #1e3a8a;
        `;
        document.body.appendChild(this.loadingIndicator);
    }

    showLoading() {
        if (this.loadingIndicator) {
            this.loadingIndicator.style.display = 'flex';
        }
    }

    hideLoading() {
        if (this.loadingIndicator) {
            this.loadingIndicator.style.display = 'none';
        }
    }

    interceptNavigation() {
        // Intercept .nav-item clicks
        document.querySelectorAll('.nav-item').forEach(link => {
            // Skip dropdown toggles
            if (link.classList.contains('dropdown-toggle')) return;
            
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const url = link.getAttribute('href');
                if (url && url !== '#') {
                    this.navigateTo(url);
                }
            });
        });
        
        // Intercept .sidebar-dropdown-item clicks
        document.querySelectorAll('.sidebar-dropdown-item').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const url = link.getAttribute('href');
                if (url && url !== '#') {
                    this.navigateTo(url);
                }
            });
        });
        
        // Also intercept any dynamic links that might be added later
        this.watchForNewLinks();
    }

    watchForNewLinks() {
        // Use MutationObserver to watch for dynamically added links
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        // Check for nav items
                        if (node.matches && node.matches('.nav-item:not(.dropdown-toggle)')) {
                            this.addLinkListener(node);
                        }
                        // Check for dropdown items
                        if (node.matches && node.matches('.sidebar-dropdown-item')) {
                            this.addDropdownListener(node);
                        }
                        // Check children
                        if (node.querySelectorAll) {
                            node.querySelectorAll('.nav-item:not(.dropdown-toggle)').forEach(link => {
                                this.addLinkListener(link);
                            });
                            node.querySelectorAll('.sidebar-dropdown-item').forEach(link => {
                                this.addDropdownListener(link);
                            });
                        }
                    }
                });
            });
        });
        
        observer.observe(document.body, { childList: true, subtree: true });
    }

    addLinkListener(link) {
        link.removeEventListener('click', this.linkHandler);
        this.linkHandler = (e) => {
            e.preventDefault();
            const url = link.getAttribute('href');
            if (url && url !== '#') {
                this.navigateTo(url);
            }
        };
        link.addEventListener('click', this.linkHandler);
    }

    addDropdownListener(link) {
        link.removeEventListener('click', this.dropdownHandler);
        this.dropdownHandler = (e) => {
            e.preventDefault();
            const url = link.getAttribute('href');
            if (url && url !== '#') {
                this.navigateTo(url);
            }
        };
        link.addEventListener('click', this.dropdownHandler);
    }

    async navigateTo(url, addToHistory = true) {
        if (url === this.currentUrl) return;
        
        // Close mobile sidebar if open
        if (window.innerWidth <= 992) {
            if (window.closeSidebar) window.closeSidebar();
        }
        
        this.showLoading();
        
        try {
            await this.loadContent(url, addToHistory);
        } catch (error) {
            console.error('Navigation error:', error);
            this.showError(url);
        } finally {
            this.hideLoading();
        }
    }

    async loadContent(url, addToHistory = true) {
        // Check cache first
        if (this.cache.has(url)) {
            console.log('Loading from cache:', url);
            this.swapContent(this.cache.get(url), url);
            if (addToHistory) {
                history.pushState({ url: url }, '', url);
            }
            this.currentUrl = url;
            this.updateActiveSidebarItem(url);
            return;
        }
        
        try {
            // Fetch content with ajax parameter support
            let fetchUrl = url;
            
            // Add ajax parameter to get only content (not full layout)
            if (url.includes('?')) {
                fetchUrl = url + '&ajax=1';
            } else {
                fetchUrl = url + '?ajax=1';
            }
            
            const response = await fetch(fetchUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                if (response.status === 404) {
                    throw new Error('Page not found');
                }
                throw new Error(`HTTP ${response.status}`);
            }
            
            const html = await response.text();
            
            // Cache the content
            this.cache.set(url, html);
            
            // Swap content
            this.swapContent(html, url);
            
            if (addToHistory) {
                history.pushState({ url: url }, '', url);
            }
            
            this.currentUrl = url;
            this.updateActiveSidebarItem(url);
            
        } catch (error) {
            console.error('Fetch error:', error);
            throw error;
        }
    }

    swapContent(html, url) {
        if (!this.mainContent) {
            console.error('Main content container not found');
            return;
        }
        
        // Add fade-out effect
        this.mainContent.style.opacity = '0';
        this.mainContent.style.transition = 'opacity 0.2s ease';
        
        setTimeout(() => {
            // Extract only the main content part if the response includes full page
            let contentHtml = html;
            
            // Try to extract only .main-content inner content if full page returned
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const extractedContent = tempDiv.querySelector('.main-content');
            
            if (extractedContent) {
                // If we got a full page response with main-content wrapper,
                // extract its inner content (excluding header which is static)
                const innerContent = extractedContent.querySelector('.container');
                if (innerContent) {
                    contentHtml = innerContent.outerHTML;
                } else {
                    contentHtml = extractedContent.innerHTML;
                }
            }
            
            // Replace content
            this.mainContent.innerHTML = contentHtml;
            
            // Re-execute scripts in the loaded content
            this.executeScripts(this.mainContent);
            
            // Fade in
            setTimeout(() => {
                this.mainContent.style.opacity = '1';
            }, 50);
            
            // Update document title if available
            const title = tempDiv.querySelector('title');
            if (title) {
                document.title = title.textContent;
            }
            
            // Trigger any custom events for loaded content
            const event = new CustomEvent('spa:contentLoaded', { detail: { url: url } });
            document.dispatchEvent(event);
            
        }, 200);
    }

    executeScripts(container) {
        const scripts = container.querySelectorAll('script');
        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            
            // Copy all attributes
            Array.from(oldScript.attributes).forEach(attr => {
                newScript.setAttribute(attr.name, attr.value);
            });
            
            // Copy inline code
            newScript.textContent = oldScript.textContent;
            
            // Replace old script with new one to execute it
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    updateActiveSidebarItem(url) {
        // Remove active class from all nav items
        document.querySelectorAll('.nav-item, .sidebar-dropdown-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Remove active class from dropdowns
        document.querySelectorAll('.nav-dropdown').forEach(dropdown => {
            dropdown.classList.remove('active');
        });
        
        // Get the current path
        const path = url.replace(/^\/|\/$/g, '');
        const page = path.split('/').pop() || 'dashboard';
        
        // Map pages to their parent dropdowns
        const registrationPages = ['classes', 'students', 'studentslist', 'teachers', 'subjects'];
        const academicPages = ['lessons', 'grading', 'exams'];
        const reportsPages = ['templates', 'reports', 'meritlist', 'analytics-page'];
        
        // Check if current page is in registration dropdown
        if (registrationPages.includes(page)) {
            const registrationDropdown = document.querySelector('.nav-dropdown:nth-child(2)');
            if (registrationDropdown) {
                registrationDropdown.classList.add('active');
            }
            const activeItem = document.querySelector(`.sidebar-dropdown-item[href$="${page}"]`);
            if (activeItem) {
                activeItem.classList.add('active');
            }
        }
        // Check if current page is in academic dropdown
        else if (academicPages.includes(page)) {
            const academicDropdown = document.querySelector('.nav-dropdown:nth-child(4)');
            if (academicDropdown) {
                academicDropdown.classList.add('active');
            }
            const activeItem = document.querySelector(`.sidebar-dropdown-item[href$="${page}"]`);
            if (activeItem) {
                activeItem.classList.add('active');
            }
        }
        // Check if current page is in reports dropdown
        else if (reportsPages.includes(page)) {
            const reportsDropdown = document.querySelector('.nav-dropdown:nth-child(6)');
            if (reportsDropdown) {
                reportsDropdown.classList.add('active');
            }
            const activeItem = document.querySelector(`.sidebar-dropdown-item[href$="${page}"]`);
            if (activeItem) {
                activeItem.classList.add('active');
            }
        }
        // Standalone pages
        else {
            const activeItem = document.querySelector(`.nav-item[href$="${page}"]`);
            if (activeItem) {
                activeItem.classList.add('active');
            }
            
            // Also check for exact match with trailing slash
            const exactMatch = document.querySelector(`.nav-item[href="${url}"]`);
            if (exactMatch) {
                exactMatch.classList.add('active');
            }
        }
        
        // Dispatch event for sidebar state change
        if (window.setActiveDropdown) {
            window.setActiveDropdown();
        }
    }

    showError(url) {
        if (this.mainContent) {
            this.mainContent.innerHTML = `
                <div class="error-container" style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 64px; color: #ef4444; margin-bottom: 20px;"></i>
                    <h2 style="color: #1f2937; margin-bottom: 10px;">Page Not Found</h2>
                    <p style="color: #6b7280; margin-bottom: 30px;">The page "${url}" could not be loaded.</p>
                    <button onclick="window.location.href='/dashboard'" class="add-event-btn" style="background: #1e3a8a;">
                        <i class="fas fa-home"></i> Go to Dashboard
                    </button>
                </div>
            `;
        }
    }

    // Clear cache for a specific URL or all URLs
    clearCache(url = null) {
        if (url) {
            this.cache.delete(url);
        } else {
            this.cache.clear();
        }
    }
}

// Initialize SPA Router when DOM is ready
let spaRouter = null;

document.addEventListener('DOMContentLoaded', () => {
    spaRouter = new SPARouter();
    
    // Expose router globally for debugging
    window.spaRouter = spaRouter;
});
executeStyles(container) {
        // Find all style tags in the loaded content
        const styles = container.querySelectorAll('style');
        const head = document.head;
        
        styles.forEach(style => {
            const styleContent = style.textContent;
            let styleExists = false;
            
            // Check if this style already exists
            document.querySelectorAll('style').forEach(existingStyle => {
                if (existingStyle.textContent === styleContent) {
                    styleExists = true;
                }
            });
            
            if (!styleExists && styleContent.trim().length > 0) {
                // Add new style to head
                const newStyle = document.createElement('style');
                newStyle.textContent = styleContent;
                head.appendChild(newStyle);
            }
        });
    }
    
    // Update swapContent to handle styles
    swapContent(html, url) {
        if (!this.mainContent) return;
        
        this.mainContent.style.opacity = '0';
        this.mainContent.style.transition = 'opacity 0.2s ease';
        
        setTimeout(() => {
            // Create a temporary div to parse the HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // Extract styles from the loaded content
            const loadedStyles = tempDiv.querySelectorAll('style');
            loadedStyles.forEach(style => {
                const styleContent = style.textContent;
                if (styleContent && styleContent.trim().length > 0) {
                    // Check if style already exists
                    let exists = false;
                    document.querySelectorAll('style').forEach(existing => {
                        if (existing.textContent === styleContent) {
                            exists = true;
                        }
                    });
                    if (!exists) {
                        const newStyle = document.createElement('style');
                        newStyle.textContent = styleContent;
                        document.head.appendChild(newStyle);
                    }
                }
            });
            
            // Extract the main content (remove sidebar and header if they appear)
            let contentHtml = html;
            const extractedContent = tempDiv.querySelector('.main-content');
            if (extractedContent) {
                // If we have a .main-content wrapper, extract its inner content
                const innerContent = extractedContent.querySelector('.container, .dashboard-header, .page-header');
                if (innerContent) {
                    contentHtml = innerContent.outerHTML;
                } else {
                    contentHtml = extractedContent.innerHTML;
                }
            } else {
                // Try to find the container
                const containerDiv = tempDiv.querySelector('.container, .page-header, .classes-table-container');
                if (containerDiv) {
                    contentHtml = containerDiv.outerHTML;
                }
            }
            
            // Replace content
            this.mainContent.innerHTML = contentHtml;
            
            // Execute scripts in the loaded content
            this.executeScripts(this.mainContent);
            
            // Re-apply any page-specific initialization
            this.reinitializePageScripts();
            
            // Fade in
            setTimeout(() => {
                this.mainContent.style.opacity = '1';
            }, 50);
            
            // Update title if available
            const title = tempDiv.querySelector('title');
            if (title) {
                document.title = title.textContent;
            }
            
            // Dispatch custom event for page loaded
            const event = new CustomEvent('spa:contentLoaded', { 
                detail: { url: url, content: this.mainContent } 
            });
            document.dispatchEvent(event);
            
        }, 200);
    }
    
    reinitializePageScripts() {
        // Re-run any page-specific initialization that might be needed
        if (typeof window.reinitializePage === 'function') {
            const currentPage = window.location.pathname.split('/').pop() || 'dashboard';
            window.reinitializePage(currentPage);
        }
        
        // Re-attach any event listeners that might have been lost
        if (typeof window.attachPageEvents === 'function') {
            window.attachPageEvents();
        }
    }
}

// Make reinitialize function available globally
window.reinitializePage = function(pageName) {
    console.log('Reinitializing page:', pageName);
    
    // Dispatch event for page-specific handlers
    const event = new CustomEvent('page:reinitialized', { 
        detail: { page: pageName } 
    });
    document.dispatchEvent(event);
};
