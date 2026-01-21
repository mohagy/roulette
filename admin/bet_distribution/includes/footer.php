    </div> <!-- End content-wrapper -->
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading data...</div>
    </div>
    
    <!-- JavaScript Test Indicator -->
    <div id="jsTestIndicator" style="position: fixed; top: 10px; right: 10px; background: #28a745; color: white; padding: 10px; border-radius: 5px; z-index: 9999; display: none;">
        ✅ JavaScript is working!
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.35.0/dist/apexcharts.min.js"></script>
    
    <!-- Three.js for 3D animations (if needed) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    
    <!-- GSAP for animations (if needed) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.9.1/gsap.min.js"></script>
    
    <!-- Firebase SDK for Firestore sync -->
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-database-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore-compat.js"></script>
    <script src="../../js/firebase-config.js"></script>
    <script src="../../js/firestore-service.js"></script>
    
    <!-- Bet Distribution Modules -->
    <script>
        // Diagnostic logging
        console.log('📄 Loading JavaScript modules...');
        console.log('📄 Current path:', window.location.pathname);
    </script>
    <script src="js/utils.js"></script>
    <script>
        console.log('✅ Utils loaded:', typeof Utils);
    </script>
    <script src="js/api-client.js"></script>
    <script>
        console.log('✅ API Client loaded:', typeof apiClient);
    </script>
    <script src="js/bet-distribution.js"></script>
    <script>
        console.log('✅ BetDistribution loaded:', typeof betDistribution);
    </script>
    <script src="js/draw-selection.js"></script>
    <script>
        console.log('✅ DrawSelection loaded:', typeof drawSelection);
    </script>
    <script src="js/draw-control.js"></script>
    <script>
        console.log('✅ DrawControl loaded:', typeof drawControl);
    </script>
    <script src="js/preset-schedule.js"></script>
    <script>
        console.log('✅ PresetSchedule loaded:', typeof presetSchedule);
    </script>
    <script src="js/forced-numbers.js"></script>
    <script>
        console.log('✅ ForcedNumbers loaded:', typeof forcedNumbers);
    </script>
    <script src="js/number-analytics.js"></script>
    <script>
        console.log('✅ NumberAnalytics loaded:', typeof numberAnalytics);
    </script>
    <script src="js/slip-analytics.js"></script>
    <script>
        console.log('✅ SlipAnalytics loaded:', typeof slipAnalytics);
    </script>
    <script src="js/main.js"></script>
    <script>
        console.log('✅ Main.js loaded');
    </script>
</body>
</html>

