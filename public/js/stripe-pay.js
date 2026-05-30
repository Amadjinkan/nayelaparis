/* ==============================================================
   NayeLa Paris — Paiement Stripe (frontend)
   ============================================================== */
(function () {
  'use strict';

  // Variables globales pour Stripe
  let stripe = null;
  let card = null;
  let cardMounted = false;

  /**
   * Initialise la page de paiement avec les données de la commande.
   * Appelée par showPage('paiement').
   */
  window.initPagePaiement = async function () {
    const cmd = window.__currentCommande;
    let pay = window.__currentPaiement;
    const errEl = document.getElementById('stripe-card-errors');
    const btnPayer = document.getElementById('btnPayer');
    const btnPayerText = document.getElementById('btnPayerText');

    if (!cmd) {
      showToast('⚠️ Aucune commande à payer');
      showPage('compte');
      return;
    }

    if (errEl) errEl.textContent = '';
    if (btnPayer) btnPayer.disabled = true;
    if (btnPayerText) btnPayerText.textContent = 'Initialisation...';

    // Récap commande
    const lignes = cmd.lignes || cmd.items || [];
    document.getElementById('paiementLignes').innerHTML = lignes.map(l => `
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed rgba(201,169,110,0.18);">
        <span>${l.emoji || '📦'} ${l.nom_produit} <span style="color:var(--gris);font-size:11px;">× ${l.quantite}${l.taille ? ' · ' + l.taille : ''}</span></span>
        <span style="font-family:'Cormorant Garamond',serif;font-size:15px;">${parseFloat(l.sous_total || (l.prix_unitaire * l.quantite)).toFixed(2)} CAD</span>
      </div>
    `).join('');

    const sousTotal = parseFloat(cmd.sous_total || 0);
    const frais = parseFloat(cmd.frais_livraison || 0);
    const taxes = parseFloat(cmd.taxes || 0);
    const total = parseFloat(cmd.total || 0);

    document.getElementById('paiementTotaux').innerHTML = `
      <div style="display:flex;justify-content:space-between;"><span>Sous-total</span><span>${sousTotal.toFixed(2)} CAD</span></div>
      <div style="display:flex;justify-content:space-between;"><span>Livraison</span><span>${frais > 0 ? frais.toFixed(2) + ' CAD' : 'Offerte'}</span></div>
      <div style="display:flex;justify-content:space-between;"><span>Taxes</span><span>${taxes.toFixed(2)} CAD</span></div>
      <div style="display:flex;justify-content:space-between;font-family:'Cormorant Garamond',serif;font-size:22px;margin-top:14px;padding-top:14px;border-top:1px solid rgba(201,169,110,0.3);">
        <strong>Total à payer</strong><strong>${total.toFixed(2)} CAD</strong>
      </div>
      <div style="text-align:center;margin-top:8px;font-size:11px;color:var(--gris);">Commande n° <strong>${cmd.numero}</strong></div>
    `;

    // Récupérer la clé publique Stripe et le client_secret.
    // Si la commande existe mais que Stripe a échoué à la création, on relance ici.
    let publishableKey = (pay && pay.publishable_key) || window.STRIPE_PUBLIC_KEY;
    if (!pay || !pay.client_secret || !publishableKey) {
      try {
        const intent = await API.creerPaymentIntent(cmd.id);
        publishableKey = intent.publishable_key;
        window.__currentPaiement = {
          client_secret: intent.client_secret,
          payment_intent_id: intent.payment_intent_id || intent.client_secret.split('_secret_')[0],
          paiement_id: intent.paiement_id || null,
          publishable_key: intent.publishable_key,
        };
        pay = window.__currentPaiement;
      } catch (err) {
        if (errEl) {
          errEl.textContent = 'Paiement Stripe indisponible : ' + (err.message || 'vérifiez STRIPE_KEY et STRIPE_SECRET dans .env.');
        }
        if (btnPayerText) btnPayerText.textContent = 'Paiement indisponible';
        showToast('⚠️ Erreur d\'initialisation du paiement : ' + (err.message || ''));
        return;
      }
    }

    if (!publishableKey || !/^pk_(test|live)_[A-Za-z0-9]{16,}$/.test(publishableKey)) {
      if (errEl) errEl.textContent =
        '⚠️ Clé Stripe non configurée. Vérifiez STRIPE_KEY dans le fichier .env du serveur.';
      if (btnPayerText) btnPayerText.textContent = 'Paiement indisponible';
      return;
    }

    // Initialiser Stripe Elements
    if (!stripe) {
      try {
        stripe = Stripe(publishableKey);
      } catch (err) {
        if (errEl) errEl.textContent = 'Impossible de charger Stripe : ' + (err.message || 'clé publique invalide.');
        if (btnPayerText) btnPayerText.textContent = 'Paiement indisponible';
        return;
      }
    }
    if (cardMounted) {
      // Reset si déjà monté
      try { card.unmount(); } catch (e) {}
      cardMounted = false;
    }
    const elements = stripe.elements({
      locale: 'fr',
      appearance: {
        theme: 'stripe',
        variables: {
          colorPrimary: '#c9a96e',
          colorBackground: '#faf8f4',
          colorText: '#1a1a18',
          fontFamily: 'Jost, sans-serif',
          borderRadius: '2px',
        },
      },
    });
    card = elements.create('card', {
      hidePostalCode: false,
      style: {
        base: {
          color: '#1a1a18',
          fontFamily: 'Jost, sans-serif',
          fontSize: '15px',
          '::placeholder': { color: '#888580' },
        },
      },
    });
    card.mount('#stripe-card-element');
    cardMounted = true;

    card.on('change', function (event) {
      const errEl = document.getElementById('stripe-card-errors');
      errEl.textContent = event.error ? event.error.message : '';
    });

    if (btnPayer) btnPayer.disabled = false;
    if (btnPayerText) btnPayerText.textContent = 'Payer maintenant';
  };

  /**
   * Soumet la carte à Stripe pour confirmer le paiement.
   */
  window.confirmerPaiementStripe = async function () {
    if (!stripe || !card) {
      showToast('⚠️ Stripe non initialisé');
      return;
    }

    const pay = window.__currentPaiement;
    if (!pay || !pay.client_secret) {
      showToast('⚠️ Aucune commande en attente');
      return;
    }

    const btn = document.getElementById('btnPayer');
    const btnText = document.getElementById('btnPayerText');
    btn.disabled = true;
    btnText.textContent = 'Paiement en cours...';

    const user = getCurrentUser();
    const cmd = window.__currentCommande;

    try {
      const result = await stripe.confirmCardPayment(pay.client_secret, {
        payment_method: {
          card: card,
          billing_details: {
            name: user ? (user.prenom + ' ' + user.nom) : '',
            email: user ? user.email : '',
            address: {
              line1: cmd.livr_ligne1 || '',
              city: cmd.livr_ville || '',
              state: cmd.livr_province || '',
              postal_code: cmd.livr_code_postal || '',
              country: 'CA',
            },
          },
        },
      });

      if (result.error) {
        document.getElementById('stripe-card-errors').textContent = result.error.message;
        showToast('⚠️ ' + result.error.message);
        btn.disabled = false;
        btnText.textContent = 'Payer maintenant';
        return;
      }

      if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
        // Confirmer côté serveur (le webhook le fera aussi mais ceci accélère le retour UI)
        try {
          await API.confirmerPaiement(result.paymentIntent.id);
        } catch (e) { /* Le webhook s'en chargera */ }

        showToast('✅ Paiement réussi ! Merci pour votre commande');
        window.__currentCommande = null;
        window.__currentPaiement = null;

        setTimeout(() => {
          showPage('compte');
          if (typeof switchAccountTab === 'function') {
            switchAccountTab('orders', null);
          }
        }, 1200);
      }
    } catch (err) {
      document.getElementById('stripe-card-errors').textContent = err.message || 'Erreur inconnue';
      btn.disabled = false;
      btnText.textContent = 'Payer maintenant';
    }
  };

  window.annulerPaiement = function () {
    if (confirm('Annuler le paiement ? Votre commande restera en attente et pourra être réglée plus tard depuis votre compte.')) {
      window.__currentCommande = null;
      window.__currentPaiement = null;
      showPage('compte');
    }
  };

})();
