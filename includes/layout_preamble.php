<script>
// Early layout preamble - runs in <head> to reduce flicker
(function(){
    try {
        var collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        // Use documentElement (html) so script can run even before body exists
        var html = document.documentElement;
        if (html) html.classList.toggle('sidebar-collapsed', collapsed);
    } catch(e) { console.warn('layout preamble failed', e); }
})();
</script>