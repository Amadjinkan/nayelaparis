/* ==============================================================
   NayeLa Paris — Client API JavaScript
   Connecte le frontend au backend Laravel (REST + Sanctum)
   ============================================================== */
(function (window) {
  'use strict';

  const BASE_URL = (window.NAYELA_API_BASE || '') + '/api';

  // ===== Stockage du token =====
  function getToken() {
    return sessionStorage.getItem('np_token') || null;
  }
  function setToken(token) {
    if (token) sessionStorage.setItem('np_token', token);
    else sessionStorage.removeItem('np_token');
  }

  // ===== Helper fetch =====
  async function request(path, options = {}) {
    const headers = {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(options.headers || {}),
    };
    if (options.body && !(options.body instanceof FormData) && typeof options.body !== 'string') {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }
    const token = getToken();
    if (token) headers['Authorization'] = 'Bearer ' + token;

    const response = await fetch(BASE_URL + path, {
      ...options,
      headers,
      credentials: 'same-origin',
    });

    // 401 = token expiré
    if (response.status === 401) {
      setToken(null);
      sessionStorage.removeItem('np_user');
    }

    let data = null;
    const text = await response.text();
    if (text) {
      try { data = JSON.parse(text); }
      catch (e) { data = { message: text }; }
    }

    if (!response.ok) {
      const error = new Error((data && data.message) || `Erreur ${response.status}`);
      error.status = response.status;
      error.data = data;
      throw error;
    }
    return data;
  }

  // ===== Objet API exposé =====
  const API = {

    // ----- Token -----
    setToken: setToken,
    getToken: getToken,

    // ----- Config publique -----
    async getConfig() {
      return request('/config');
    },

    async getMenu() {
      return request('/menu');
    },

    async getSiteContent() {
      return request('/site/content');
    },

    async getCategories() {
      return request('/site/categories');
    },

    async getRecommandations(params) {
      const q = new URLSearchParams(params || {}).toString();
      return request('/marketing/recommandes' + (q ? '?' + q : ''));
    },

    async getProduitsSimilaires(id) {
      return request('/produits/' + id + '/similaires');
    },

    async enregistrerVueProduit(id, sessionId) {
      return request('/produits/' + id + '/vue', {
        method: 'POST',
        body: { session_id: sessionId || '' },
      });
    },

    async getProduitsRecents(sessionId) {
      const q = sessionId ? '?session_id=' + encodeURIComponent(sessionId) : '';
      return request('/marketing/recemment-vus' + q);
    },

    async verifierCoupon(code, subtotal, shipping) {
      return request('/marketing/coupons/verifier', {
        method: 'POST',
        body: { code, subtotal, shipping },
      });
    },

    async getAvisProduit(id) {
      return request('/produits/' + id + '/avis');
    },

    async envoyerContact(payload) {
      return request('/contact', {
        method: 'POST',
        body: payload,
      });
    },

    async sendContact(payload) {
      return this.envoyerContact(payload);
    },

    // ----- Auth -----
    async register(prenom, nom, email, mot_de_passe, newsletter) {
      const data = await request('/auth/register', {
        method: 'POST',
        body: { prenom, nom, email, mot_de_passe, mot_de_passe_confirmation: mot_de_passe, newsletter: !!newsletter },
      });
      if (data.token) setToken(data.token);
      if (data.user) sessionStorage.setItem('np_user', JSON.stringify(data.user));
      return data;
    },

    async login(email, mot_de_passe) {
      const data = await request('/auth/login', {
        method: 'POST',
        body: { email, mot_de_passe },
      });
      if (data.token) setToken(data.token);
      if (data.user) sessionStorage.setItem('np_user', JSON.stringify(data.user));
      return data;
    },

    async logout() {
      try { await request('/auth/logout', { method: 'POST' }); } catch (e) {}
      setToken(null);
      sessionStorage.removeItem('np_user');
    },

    async me() {
      return request('/auth/me');
    },

    async demanderResetMotDePasse(payload) {
      return request('/auth/password/forgot', {
        method: 'POST',
        body: payload,
      });
    },

    async requestPasswordReset(payload) {
      return this.demanderResetMotDePasse(payload);
    },

    async forgotPassword(payload) {
      return this.demanderResetMotDePasse(payload);
    },

    // ----- Produits -----
    async getProduits(filtres) {
      const params = new URLSearchParams(filtres || {}).toString();
      return request('/produits' + (params ? '?' + params : ''));
    },

    async getProduit(id) {
      return request('/produits/' + id);
    },

    async creerProduit(produit) {
      return request('/admin/produits', { method: 'POST', body: produit });
    },

    async updateProduit(payload) {
      const id = payload.id;
      delete payload.id;
      return request('/admin/produits/' + id, { method: 'PUT', body: payload });
    },

    async supprimerProduit(id) {
      return request('/admin/produits/' + id, { method: 'DELETE' });
    },

    async mesFavoris() {
      return request('/marketing/favoris');
    },

    async ajouterFavori(id) {
      return request('/marketing/favoris/' + id, { method: 'POST' });
    },

    async supprimerFavori(id) {
      return request('/marketing/favoris/' + id, { method: 'DELETE' });
    },

    async ajouterAvisProduit(id, payload) {
      return request('/produits/' + id + '/avis', { method: 'POST', body: payload });
    },

    async fidelite() {
      return request('/marketing/fidelite');
    },

    // ----- Profil & adresses -----
    async getProfil() {
      const data = await request('/profil');
      return { ...(data.user || {}), adresses: data.adresses || [] };
    },

    async updateProfil(payload) {
      // Si mot de passe : ajouter la confirmation
      if (payload.mot_de_passe) {
        payload.mot_de_passe_confirmation = payload.mot_de_passe;
      }
      return request('/profil', { method: 'PUT', body: payload });
    },

    async ajouterAdresse(adresse) {
      return request('/profil/adresses', { method: 'POST', body: adresse });
    },

    async supprimerAdresse(id) {
      return request('/profil/adresses/' + id, { method: 'DELETE' });
    },

    // ----- Commandes -----
    async mesCommandes() {
      return request('/commandes');
    },

    async getCommande(id) {
      return request('/commandes/' + id);
    },

    async telechargerFacture(id) {
      const headers = {
        'Accept': 'text/html',
        'X-Requested-With': 'XMLHttpRequest',
      };
      const token = getToken();
      if (token) headers['Authorization'] = 'Bearer ' + token;

      const response = await fetch(BASE_URL + '/commandes/' + id + '/facture', {
        headers,
        credentials: 'same-origin',
      });

      if (!response.ok) {
        let message = `Erreur ${response.status}`;
        try {
          const data = await response.json();
          message = data.message || message;
        } catch (e) {}
        throw new Error(message);
      }

      const disposition = response.headers.get('Content-Disposition') || '';
      const match = disposition.match(/filename="?([^"]+)"?/i);
      return {
        blob: await response.blob(),
        filename: match ? match[1] : 'facture-nayela-paris.html',
      };
    },

    async passerCommande(articles, adresseId, codePromo) {
      return request('/commandes', {
        method: 'POST',
        body: { articles, adresse_id: adresseId || null, code_promo: codePromo || null },
      });
    },

    // ----- Paiement Stripe -----
    async creerPaymentIntent(commandeId) {
      return request('/paiements/intent', {
        method: 'POST',
        body: { commande_id: commandeId },
      });
    },

    async confirmerPaiement(paymentIntentId) {
      return request('/paiements/confirmer', {
        method: 'POST',
        body: { payment_intent_id: paymentIntentId },
      });
    },

    // ----- Retours (RMA) -----
    async mesRetours() {
      return request('/retours');
    },

    async demanderRetour(payload) {
      return request('/retours', { method: 'POST', body: payload });
    },

    async getRetour(id) {
      return request('/retours/' + id);
    },

    // ----- Admin -----
    async adminStats() {
      return request('/admin/statistiques');
    },

    async adminDashboard(params) {
      const q = new URLSearchParams(params || {}).toString();
      return request('/admin/dashboard' + (q ? '?' + q : ''));
    },

    async adminProduits() {
      return request('/admin/produits');
    },

    async adminMenu() {
      return request('/admin/menu');
    },

    async adminSiteContent() {
      return request('/admin/site/content');
    },

    async adminUpdateSiteSettings(settings) {
      return request('/admin/site/settings', {
        method: 'PUT',
        body: { settings },
      });
    },

    async adminCreateCategory(payload) {
      return request('/admin/site/categories', { method: 'POST', body: payload });
    },

    async adminUpdateCategory(id, payload) {
      return request('/admin/site/categories/' + id, { method: 'PUT', body: payload });
    },

    async adminDeleteCategory(id) {
      return request('/admin/site/categories/' + id, { method: 'DELETE' });
    },

    async adminCreateBanner(payload) {
      return request('/admin/site/banners', { method: 'POST', body: payload });
    },

    async adminUpdateBanner(id, payload) {
      return request('/admin/site/banners/' + id, { method: 'PUT', body: payload });
    },

    async adminDeleteBanner(id) {
      return request('/admin/site/banners/' + id, { method: 'DELETE' });
    },

    async adminCreatePage(payload) {
      return request('/admin/site/pages', { method: 'POST', body: payload });
    },

    async adminUpdatePage(id, payload) {
      return request('/admin/site/pages/' + id, { method: 'PUT', body: payload });
    },

    async adminDeletePage(id) {
      return request('/admin/site/pages/' + id, { method: 'DELETE' });
    },

    async adminCreateMenuItem(payload) {
      return request('/admin/menu', { method: 'POST', body: payload });
    },

    async adminUpdateMenuItem(id, payload) {
      return request('/admin/menu/' + id, { method: 'PUT', body: payload });
    },

    async adminDeleteMenuItem(id) {
      return request('/admin/menu/' + id, { method: 'DELETE' });
    },

    async adminReorderMenu(items) {
      return request('/admin/menu/reorder', {
        method: 'POST',
        body: { items },
      });
    },

    async adminCommandes(statut) {
      const q = statut ? '?statut=' + statut : '';
      return request('/admin/commandes' + q);
    },

    async adminUpdateCommande(id, payload) {
      return request('/admin/commandes/' + id, { method: 'PUT', body: payload });
    },

    async adminRetours(statut) {
      const q = statut ? '?statut=' + statut : '';
      return request('/admin/retours' + q);
    },

    async adminApprouverRetour(id, note) {
      return request('/admin/retours/' + id + '/approuver', {
        method: 'POST',
        body: { note_admin: note || '' },
      });
    },

    async adminRefuserRetour(id, motif) {
      return request('/admin/retours/' + id + '/refuser', {
        method: 'POST',
        body: { motif_refus: motif },
      });
    },

    async adminRembourserRetour(id) {
      return request('/admin/retours/' + id + '/rembourser', { method: 'POST' });
    },

    async adminCoupons() {
      return request('/admin/coupons');
    },

    async adminCreateCoupon(payload) {
      return request('/admin/coupons', { method: 'POST', body: payload });
    },

    async adminUpdateCoupon(id, payload) {
      return request('/admin/coupons/' + id, { method: 'PUT', body: payload });
    },

    async adminDeleteCoupon(id) {
      return request('/admin/coupons/' + id, { method: 'DELETE' });
    },

    async adminAvis() {
      return request('/admin/avis');
    },

    async adminApproveAvis(id) {
      return request('/admin/avis/' + id + '/approuver', { method: 'POST' });
    },

    async adminRejectAvis(id) {
      return request('/admin/avis/' + id + '/refuser', { method: 'POST' });
    },
  };

  window.API = API;

})(window);
