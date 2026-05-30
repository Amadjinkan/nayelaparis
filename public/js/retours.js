/* ==============================================================
   NayeLa Paris — Page demande de retour (RMA)
   ============================================================== */
(function () {
  'use strict';

  let __retoursCommandes = []; // Commandes éligibles chargées depuis l'API

  /**
   * Initialise la page de retour : charge les commandes éligibles.
   * Appelée par showPage('retour').
   */
  window.initPageRetour = async function () {
    const select = document.getElementById('retourCommandeSelect');
    const empty = document.getElementById('retourEmpty');
    const articlesBlock = document.getElementById('retourArticlesBlock');
    const motifBlock = document.getElementById('retourMotifBlock');

    // Reset
    select.innerHTML = '<option value="">— Chargement des commandes... —</option>';
    articlesBlock.style.display = 'none';
    motifBlock.style.display = 'none';
    empty.style.display = 'none';

    try {
      const all = await API.mesCommandes();
      __retoursCommandes = (all || []).filter(c => c.peut_etre_retournee || c.statut === 'delivered');

      if (!__retoursCommandes.length) {
        select.innerHTML = '<option value="">— Aucune commande éligible —</option>';
        empty.style.display = 'block';
        return;
      }

      select.innerHTML = '<option value="">— Sélectionnez une commande —</option>' +
        __retoursCommandes.map(c => `
          <option value="${c.id}">${c.numero} · ${c.date_commande} · ${parseFloat(c.total).toFixed(2)} CAD</option>
        `).join('');
    } catch (err) {
      select.innerHTML = '<option value="">— Erreur de chargement —</option>';
      empty.style.display = 'block';
      empty.innerHTML = '⚠️ ' + (err.message || 'Impossible de charger vos commandes.');
    }
  };

  window.onChangeCommandeRetour = function () {
    const id = parseInt(document.getElementById('retourCommandeSelect').value);
    const articlesBlock = document.getElementById('retourArticlesBlock');
    const motifBlock = document.getElementById('retourMotifBlock');
    const list = document.getElementById('retourArticlesList');

    if (!id) {
      articlesBlock.style.display = 'none';
      motifBlock.style.display = 'none';
      return;
    }

    const cmd = __retoursCommandes.find(c => c.id === id);
    if (!cmd) return;

    list.innerHTML = (cmd.lignes || []).map(l => `
      <div style="display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px dashed rgba(201,169,110,0.18);">
        <input type="checkbox" id="retArt-${l.id}" data-line="${l.id}" data-max="${l.quantite}" data-price="${l.prix_unitaire}" onchange="toggleArticleRetour(${l.id})" style="width:18px;height:18px;cursor:pointer;">
        <div style="flex:1;">
          <div style="font-size:13px;">${l.emoji || '📦'} <strong>${l.nom_produit}</strong>${l.taille ? ' · ' + l.taille : ''}</div>
          <div style="font-size:11px;color:var(--gris);">Commandé : ${l.quantite} · Prix unitaire : ${parseFloat(l.prix_unitaire).toFixed(2)} CAD</div>
        </div>
        <div>
          <label style="font-size:10px;letter-spacing:0.14em;text-transform:uppercase;color:var(--gris);">Qté à retourner</label>
          <input type="number" id="retQty-${l.id}" value="1" min="1" max="${l.quantite}" disabled style="width:70px;padding:8px;border:1px solid rgba(201,169,110,0.4);font-family:'Jost',sans-serif;font-size:13px;text-align:center;margin-left:8px;">
        </div>
      </div>
    `).join('');

    articlesBlock.style.display = 'block';
    motifBlock.style.display = 'block';
  };

  window.toggleArticleRetour = function (ligneId) {
    const cb = document.getElementById('retArt-' + ligneId);
    const qty = document.getElementById('retQty-' + ligneId);
    qty.disabled = !cb.checked;
    if (!cb.checked) qty.value = 1;
  };

  window.envoyerDemandeRetour = async function () {
    const cmdId = parseInt(document.getElementById('retourCommandeSelect').value);
    if (!cmdId) { showToast('⚠️ Sélectionnez une commande'); return; }

    const motif = document.getElementById('retourMotif').value;
    const desc = document.getElementById('retourDescription').value.trim();
    const note = document.getElementById('retourNote').value.trim();

    if (!desc) { showToast('⚠️ Veuillez décrire le problème'); return; }
    if (desc.length < 10) { showToast('⚠️ Description trop courte (10 caractères minimum)'); return; }

    // Collecter les articles cochés
    const articles = [];
    document.querySelectorAll('#retourArticlesList input[type=checkbox]:checked').forEach(cb => {
      const ligneId = parseInt(cb.getAttribute('data-line'));
      const qty = parseInt(document.getElementById('retQty-' + ligneId).value);
      if (qty > 0) articles.push({ ligne_commande_id: ligneId, quantite: qty });
    });

    if (!articles.length) { showToast('⚠️ Cochez au moins un article à retourner'); return; }

    try {
      const result = await API.demanderRetour({
        commande_id: cmdId,
        motif: motif,
        description: desc,
        note_client: note,
        articles: articles,
      });
      showToast('✅ Demande envoyée — Numéro RMA : ' + result.retour.numero_rma);
      setTimeout(() => {
        showPage('compte');
        if (typeof switchAccountTab === 'function') {
          switchAccountTab('orders', null);
        }
      }, 1500);
    } catch (err) {
      showToast('⚠️ ' + (err.message || 'Erreur lors de l\'envoi'));
    }
  };

})();
