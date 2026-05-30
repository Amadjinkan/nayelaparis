/* NayeLa Paris - Admin Retours (RMA) */
(function () {
  'use strict';

  window.loadAdminRetours = async function () {
    const container = document.getElementById('adminRetoursList');
    if (!container) return;
    container.innerHTML = '<div style="padding:40px;text-align:center;color:var(--gris);">Chargement...</div>';
    try {
      const retours = await API.adminRetours();
      if (!retours.length) {
        container.innerHTML = '<div style="padding:40px;text-align:center;color:var(--gris);">Aucune demande de retour pour le moment.</div>';
        return;
      }
      container.innerHTML = retours.map(r => renderRetourCard(r)).join('');
    } catch (err) {
      container.innerHTML = '<div style="padding:40px;color:#c0392b;">⚠️ Erreur : ' + (err.message || 'Impossible de charger') + '</div>';
    }
  };

  function renderRetourCard(r) {
    const statutColors = {'demande':'#f39c12','approuve':'#3498db','refuse':'#c0392b','attendu':'#9b59b6','recu':'#16a085','rembourse':'#27ae60','clos':'#95a5a6'};
    const statutLabels = {'demande':'📝 Demandé','approuve':'✅ Approuvé','refuse':'❌ Refusé','attendu':'📦 En attente du colis','recu':'✓ Colis reçu','rembourse':'💰 Remboursé','clos':'🔒 Clos'};
    const motifLabels = {'taille_incorrecte':'Taille incorrecte','defaut_qualite':'Défaut de qualité','non_conforme':'Non conforme','recu_endommage':'Reçu endommagé','autre':'Autre raison'};
    const couleur = statutColors[r.statut] || '#95a5a6';
    const statutLabel = statutLabels[r.statut] || r.statut;
    const articles = (r.lignes || []).map(l =>
      `<li>${l.ligne_commande?.emoji || '📦'} ${l.ligne_commande?.nom_produit || 'Article'} × ${l.quantite} <strong>(${parseFloat(l.montant).toFixed(2)} CAD)</strong></li>`
    ).join('');
    const total = (r.lignes || []).reduce((sum, l) => sum + parseFloat(l.montant), 0);

    return `<div style="background:var(--blanc);border:1px solid rgba(201,169,110,0.25);padding:24px;margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
        <div style="flex:1;min-width:300px;">
          <div style="font-family:'Cormorant Garamond',serif;font-size:20px;margin-bottom:4px;"><strong>${r.numero_rma}</strong></div>
          <div style="font-size:11px;color:var(--gris);letter-spacing:0.06em;">Commande ${r.commande?.numero || '-'} · Client : ${r.user?.prenom || ''} ${r.user?.nom || ''} (${r.user?.email || ''})</div>
          <div style="font-size:11px;color:var(--gris);">Demandé le ${new Date(r.created_at).toLocaleDateString('fr-CA')}</div>
        </div>
        <div style="background:${couleur};color:white;padding:6px 14px;font-size:11px;letter-spacing:0.12em;text-transform:uppercase;">${statutLabel}</div>
      </div>
      <div style="margin-top:18px;padding-top:18px;border-top:1px dashed rgba(201,169,110,0.25);">
        <div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:var(--gris);margin-bottom:6px;">Motif</div>
        <div style="font-size:13px;margin-bottom:14px;"><strong>${motifLabels[r.motif] || r.motif}</strong></div>
        <div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:var(--gris);margin-bottom:6px;">Description du client</div>
        <div style="font-size:13px;line-height:1.7;margin-bottom:14px;padding:12px;background:var(--creme);border-left:3px solid var(--or);">${escapeHtml(r.description || '-')}</div>
        ${r.note_client ? `<div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:var(--gris);margin-bottom:6px;">Note du client</div><div style="font-size:13px;margin-bottom:14px;color:var(--gris);">${escapeHtml(r.note_client)}</div>` : ''}
        <div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:var(--gris);margin-bottom:6px;">Articles à retourner</div>
        <ul style="font-size:13px;line-height:1.9;margin:0 0 14px 20px;">${articles}</ul>
        <div style="font-size:13px;text-align:right;padding-top:8px;border-top:1px solid rgba(201,169,110,0.18);"><strong>Total à rembourser : ${total.toFixed(2)} CAD</strong></div>
        ${r.note_admin ? `<div style="margin-top:14px;padding:12px;background:#f0f8ff;border-left:3px solid #3498db;"><strong style="font-size:11px;letter-spacing:0.12em;text-transform:uppercase;">Note admin :</strong><div style="font-size:13px;margin-top:4px;">${escapeHtml(r.note_admin)}</div></div>` : ''}
        ${r.motif_refus ? `<div style="margin-top:14px;padding:12px;background:#fff0f0;border-left:3px solid #c0392b;"><strong style="font-size:11px;letter-spacing:0.12em;text-transform:uppercase;">Motif du refus :</strong><div style="font-size:13px;margin-top:4px;">${escapeHtml(r.motif_refus)}</div></div>` : ''}
        ${r.stripe_refund_id ? `<div style="margin-top:14px;padding:12px;background:#f0fff4;border-left:3px solid #27ae60;"><strong style="font-size:11px;letter-spacing:0.12em;text-transform:uppercase;">Remboursement Stripe :</strong><div style="font-size:11px;font-family:monospace;margin-top:4px;color:var(--gris);">${r.stripe_refund_id}</div><div style="font-size:13px;margin-top:2px;">Montant : <strong>${parseFloat(r.montant_rembourse).toFixed(2)} CAD</strong></div></div>` : ''}
      </div>
      ${renderActions(r, total)}
    </div>`;
  }

  function renderActions(r, total) {
    if (r.statut === 'demande') {
      return `<div style="margin-top:18px;padding-top:18px;border-top:1px solid rgba(201,169,110,0.18);display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn-primary" style="flex:1;min-width:140px;" onclick="approuverRetour(${r.id})">✅ Approuver</button>
        <button class="btn-outline" style="flex:1;min-width:140px;color:#c0392b;border-color:#c0392b;" onclick="refuserRetour(${r.id})">❌ Refuser</button>
      </div>`;
    }
    if (r.statut === 'approuve' || r.statut === 'recu') {
      return `<div style="margin-top:18px;padding-top:18px;border-top:1px solid rgba(201,169,110,0.18);display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn-primary" style="flex:1;background:#27ae60;border-color:#27ae60;" onclick="rembourserRetour(${r.id}, ${total.toFixed(2)})">💰 Déclencher le remboursement Stripe (${total.toFixed(2)} CAD)</button>
      </div>`;
    }
    return '';
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  window.approuverRetour = async function (id) {
    const note = prompt('Note pour le client (facultatif) :', '');
    if (note === null) return;
    try { await API.adminApprouverRetour(id, note); showToast('✅ Retour approuvé'); loadAdminRetours(); }
    catch (err) { showToast('⚠️ ' + (err.message || 'Erreur')); }
  };

  window.refuserRetour = async function (id) {
    const motif = prompt('Motif du refus (obligatoire) :', '');
    if (!motif) { if (motif === '') showToast('⚠️ Motif obligatoire'); return; }
    try { await API.adminRefuserRetour(id, motif); showToast('Retour refusé'); loadAdminRetours(); }
    catch (err) { showToast('⚠️ ' + (err.message || 'Erreur')); }
  };

  window.rembourserRetour = async function (id, montant) {
    if (!confirm(`Confirmer le remboursement de ${montant} CAD via Stripe ?\nCette action est IRRÉVERSIBLE.`)) return;
    try { await API.adminRembourserRetour(id); showToast('💰 Remboursement effectué : ' + montant + ' CAD'); loadAdminRetours(); }
    catch (err) { showToast('⚠️ ' + (err.message || 'Erreur de remboursement')); }
  };
})();
