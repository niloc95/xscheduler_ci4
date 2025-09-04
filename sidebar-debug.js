// Sidebar Diagnostic Tool
// Add this to browser console to debug sidebar positioning issues

const debugSidebar = () => {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) {
        console.log('❌ No sidebar element found');
        return;
    }
    
    console.log('🔍 SIDEBAR DEBUG INFO:');
    console.log('='.repeat(50));
    
    const computed = getComputedStyle(sidebar);
    const rect = sidebar.getBoundingClientRect();
    
    console.log('📍 POSITION PROPERTIES:');
    console.log(`   position: ${computed.position}`);
    console.log(`   top: ${computed.top}`);
    console.log(`   left: ${computed.left}`);
    console.log(`   z-index: ${computed.zIndex}`);
    console.log(`   transform: ${computed.transform}`);
    
    console.log('📏 DIMENSIONS:');
    console.log(`   width: ${computed.width}`);
    console.log(`   height: ${computed.height}`);
    
    console.log('🖼️ BOUNDING RECT:');
    console.log(`   top: ${rect.top}`);
    console.log(`   left: ${rect.left}`);
    console.log(`   width: ${rect.width}`);
    console.log(`   height: ${rect.height}`);
    
    console.log('📱 RESPONSIVE:');
    console.log(`   screen width: ${window.innerWidth}`);
    console.log(`   is mobile: ${window.innerWidth < 1024 ? 'YES' : 'NO'}`);
    
    console.log('🏷️ CSS CLASSES:');
    console.log(`   classes: ${sidebar.className}`);
    
    console.log('🎯 EXPECTED BEHAVIOR:');
    if (window.innerWidth >= 1024) {
        console.log('   - Should be fixed positioned');
        console.log('   - Should stay at top:0 left:0');
        console.log('   - Should not move when scrolling');
    } else {
        console.log('   - Should be hidden (-100% transform)');
        console.log('   - Should slide in when .open class added');
    }
    
    // Test scrolling behavior
    const initialTop = rect.top;
    console.log('📜 SCROLL TEST:');
    console.log(`   Initial top position: ${initialTop}`);
    
    window.scrollTo(0, 100);
    setTimeout(() => {
        const newRect = sidebar.getBoundingClientRect();
        const moved = Math.abs(newRect.top - initialTop) > 1;
        console.log(`   After scroll top position: ${newRect.top}`);
        console.log(`   Moved with scroll: ${moved ? '❌ YES (should be fixed!)' : '✅ NO (correct)'}`);
        
        // Restore scroll
        window.scrollTo(0, 0);
    }, 100);
};

// Run immediately
debugSidebar();

console.log('💡 To re-run this diagnostic, type: debugSidebar()');
