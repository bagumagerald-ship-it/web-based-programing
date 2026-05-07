/* ================================================================
   UMU Voting System — assets/main.js
   ================================================================ */
'use strict';

// ── Candidate card selection ──────────────────────────────────
document.querySelectorAll('.candidate-card').forEach(card => {
  card.addEventListener('click', () => {
    const radio = card.querySelector('input[type="radio"]');
    if (!radio) return;
    const posId = radio.dataset.position;

    // Deselect others in same position
    document.querySelectorAll(`.candidate-card input[data-position="${posId}"]`)
      .forEach(r => {
        r.checked = false;
        r.closest('.candidate-card').classList.remove('selected');
      });

    radio.checked = true;
    card.classList.add('selected');
    updateVoteStatus();
  });
});

function updateVoteStatus() {
  const total    = document.querySelectorAll('.position-block').length;
  const selected = document.querySelectorAll('.candidate-card.selected').length;
  const txt      = document.getElementById('voteStatusText');
  const btn      = document.getElementById('submitVoteBtn');
  if (txt) txt.innerHTML = `Selected <strong>${selected}</strong> of <strong>${total}</strong> position${total !== 1 ? 's' : ''}`;
  if (btn) {
    btn.disabled = selected < total;
    btn.className = selected >= total
      ? 'btn btn-primary'
      : 'btn btn-secondary';
  }
}
updateVoteStatus();

// ── Vote confirmation modal ───────────────────────────────────
const voteForm      = document.getElementById('voteForm');
const submitVoteBtn = document.getElementById('submitVoteBtn');
const confirmModal  = document.getElementById('confirmVoteModal');
const confirmBtn    = document.getElementById('confirmSubmitBtn');
const cancelBtn     = document.getElementById('cancelSubmitBtn');

if (submitVoteBtn && confirmModal) {
  submitVoteBtn.addEventListener('click', () => {
    // Build summary
    const summaryEl = document.getElementById('voteSummary');
    if (summaryEl) {
      const cards = document.querySelectorAll('.candidate-card.selected');
      let html = '<ul style="padding:0">';
      cards.forEach(card => {
        const titleEl = card.closest('.position-block')?.querySelector('.position-title');
        // position-title contains badge span; take only the text part
        const pos = titleEl ? (titleEl.childNodes[0]?.textContent?.trim() || titleEl.textContent.trim()) : '';

        const name = card.querySelector('.candidate-name').textContent.trim();
        html += `<li style="display:flex;justify-content:space-between;align-items:center;
                   padding:.55rem 0;border-bottom:1px solid #eee">
                   <span style="color:#666;font-size:.83rem">${pos}</span>
                   <strong style="font-size:.9rem">${name}</strong></li>`;
      });
      html += '</ul>';
      summaryEl.innerHTML = html;
    }
    confirmModal.classList.add('open');
  });
}
if (cancelBtn)   cancelBtn.addEventListener('click',  () => confirmModal.classList.remove('open'));
if (confirmModal) confirmModal.addEventListener('click', e => { if (e.target === confirmModal) confirmModal.classList.remove('open'); });
if (confirmBtn && voteForm) {
  confirmBtn.addEventListener('click', () => {
    confirmBtn.textContent = 'Submitting…';
    confirmBtn.disabled = true;
    voteForm.submit();
  });
}

// ── Generic modal open/close ─────────────────────────────────
document.querySelectorAll('[data-modal-open]').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById(btn.dataset.modalOpen)?.classList.add('open');
  });
});
document.querySelectorAll('[data-modal-close]').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById(btn.dataset.modalClose)?.classList.remove('open');
  });
});

// ── Edit-candidate modal auto-fill ───────────────────────────
document.querySelectorAll('[data-edit-candidate]').forEach(btn => {
  btn.addEventListener('click', () => {
    const modal = document.getElementById('editCandidateModal');
    if (!modal) return;
    const d = btn.dataset;
    modal.querySelector('[name="candidate_id"]').value = d.editCandidate;
    modal.querySelector('[name="full_name"]').value    = d.name;
    modal.querySelector('[name="position_id"]').value  = d.position;
    modal.querySelector('[name="manifesto"]').value    = d.manifesto;
    modal.classList.add('open');
  });
});

// ── Live table search ────────────────────────────────────────
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
  searchInput.addEventListener('input', () => {
    const q = searchInput.value.toLowerCase();
    document.querySelectorAll('[data-search-row]').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ── Auto-dismiss alerts ──────────────────────────────────────
document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity .5s';
    el.style.opacity    = '0';
    setTimeout(() => el.remove(), 550);
  }, 4000);
});

// ── Confirm-before-submit ────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(btn => {
  btn.addEventListener('click', e => {
    if (!confirm(btn.dataset.confirm)) e.preventDefault();
  });
});

// ── Animate vote/result bars ─────────────────────────────────
document.querySelectorAll('.vote-bar-fill[data-pct]').forEach(bar => {
  const pct = parseFloat(bar.dataset.pct) || 0;
  bar.style.width = '0%';
  requestAnimationFrame(() => setTimeout(() => { bar.style.width = pct + '%'; }, 120));
});

// ── Password strength ────────────────────────────────────────
const pwInput    = document.getElementById('password');
const pwStrength = document.getElementById('pwStrength');
if (pwInput && pwStrength) {
  pwInput.addEventListener('input', () => {
    const v = pwInput.value;
    let score = 0;
    if (v.length >= 8)          score++;
    if (/[A-Z]/.test(v))        score++;
    if (/[0-9]/.test(v))        score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const labels = ['','Weak','Fair','Good','Strong'];
    const colors = ['','#c0001a','#e08000','#c99400','#1a7a3c'];
    pwStrength.textContent = labels[score] || '';
    pwStrength.style.color = colors[score] || '';
  });
}

// ── CSV auto-submit on file pick ─────────────────────────────
const csvInput = document.getElementById('csvUpload');
if (csvInput) {
  csvInput.addEventListener('change', function() {
    if (this.files.length) this.closest('form').submit();
  });
}

// ── CSV template download ────────────────────────────────────
window.downloadCSVTemplate = function() {
  const csv = 'reg_number,full_name,email,password\n'
    + '2023-B072-31712,Baguma Gerald,baguma.gerald@stud.umu.ac.ug,Pass@1234\n'
    + '2022-A015-20301,Akello Mary,akello.mary@stud.umu.ac.ug,Secure@456\n';
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'umu_voters_template.csv';
  a.click();
};
