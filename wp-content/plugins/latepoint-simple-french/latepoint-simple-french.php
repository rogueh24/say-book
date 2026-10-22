<?php
/**
 * Plugin Name: LatePoint Simple French Fix
 * Description: Traduit "Sign in to your account" et "Forgot?" en français
 * Version: 1.0.0
 * Author: Votre Nom
 */

if (!defined('ABSPATH')) {
    exit;
}

// Traduction JavaScript simple
add_action('wp_footer', function() {
    ?>
    <script type="text/javascript">
    (function() {
        'use strict';
        
        function translateTexts() {
            // Traduire tous les éléments du DOM
            document.querySelectorAll('*').forEach(function(element) {
                // Traduire le texte des nœuds
                element.childNodes.forEach(function(node) {
                    if (node.nodeType === 3) { // Text node
                        if (node.textContent.includes('Sign in to your account')) {
                            node.textContent = node.textContent.replace('Sign in to your account', 'Connectez-vous à votre compte');
                        }
                        if (node.textContent.includes('Forgot?')) {
                            node.textContent = node.textContent.replace('Forgot?', 'Mot de passe oublié ?');
                        }
                    }
                });
            });
        }
        
        // Exécuter au chargement
        translateTexts();
        
        // Observer les changements DOM
        var observer = new MutationObserver(translateTexts);
        observer.observe(document.body, { childList: true, subtree: true });
        
        // Exécuter toutes les 500ms pendant 5 secondes
        var count = 0;
        var interval = setInterval(function() {
            translateTexts();
            count++;
            if (count > 10) clearInterval(interval);
        }, 500);
        
    })();
    </script>
    <?php
}, 999);
