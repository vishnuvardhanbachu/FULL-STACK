/* ═══════════════════════════════════════════════════════════════
   Job Portal — Global JavaScript
   ═══════════════════════════════════════════════════════════════ */

(function () {
  'use strict';

  /* ── Sidebar Toggle ──────────────────────────────────────── */
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const hamburger= document.getElementById('hamburger');

  function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('open');
    document.body.style.overflow = '';
  }

  hamburger?.addEventListener('click', openSidebar);
  overlay?.addEventListener('click', closeSidebar);

  // Close sidebar on nav-item click (mobile)
  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', () => {
      if (window.innerWidth < 900) closeSidebar();
    });
  });

  /* ── Active nav highlighting ─────────────────────────────── */
  const currentPath = window.location.pathname.split('/').pop();
  document.querySelectorAll('.nav-item').forEach(item => {
    const href = item.getAttribute('href') || '';
    if (href && href.split('/').pop() === currentPath) {
      item.classList.add('active');
    }
  });

  /* ── Auto-dismiss alerts ─────────────────────────────────── */
  document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s ease, max-height 0.5s ease';
      alert.style.opacity    = '0';
      alert.style.maxHeight  = '0';
      alert.style.overflow   = 'hidden';
      alert.style.padding    = '0';
      alert.style.margin     = '0';
      setTimeout(() => alert.remove(), 600);
    }, 4500);
  });

  /* ── Notification bell mark-as-read ─────────────────────── */
  const notifBtn = document.getElementById('notifBtn');
  notifBtn?.addEventListener('click', () => {
    fetch('../api.php?action=mark_read', { method: 'POST' })
      .then(() => {
        const dot = notifBtn.querySelector('.notif-dot');
        if (dot) dot.remove();
      })
      .catch(() => {}); // silently fail if not logged in
  });

  /* ── Smooth animate-in on scroll ────────────────────────── */
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.style.animationPlayState = 'running';
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12 }
    );

    document.querySelectorAll('.animate-in').forEach(el => {
      el.style.animationPlayState = 'paused';
      observer.observe(el);
    });
  }

  /* ── Form validation enhancements ───────────────────────── */
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('invalid', function (e) {
      e.preventDefault();
      const input = e.target;
      input.style.borderColor = 'var(--danger)';
      input.style.boxShadow   = '0 0 0 3px rgba(239,68,68,0.2)';

      // Remove error styles on fix
      input.addEventListener('input', function fix() {
        input.style.borderColor = '';
        input.style.boxShadow   = '';
        input.removeEventListener('input', fix);
      }, { once: true });
    }, true);
  });

  /* ── Salary range auto-validate (post-job form) ─────────── */
  const salMin = document.querySelector('[name="salary_min"]');
  const salMax = document.querySelector('[name="salary_max"]');
  if (salMin && salMax) {
    function checkSalary() {
      const mn = parseInt(salMin.value) || 0;
      const mx = parseInt(salMax.value) || 0;
      if (mx > 0 && mn > mx) {
        salMax.setCustomValidity('Max salary must be ≥ min salary');
      } else {
        salMax.setCustomValidity('');
      }
    }
    salMin.addEventListener('input', checkSalary);
    salMax.addEventListener('input', checkSalary);
  }

  /* ── Password strength indicator ────────────────────────── */
  const pwdInput = document.querySelector('[name="new_password"], [name="password"]');
  if (pwdInput) {
    const hint = document.createElement('div');
    hint.className = 'form-hint';
    hint.style.transition = 'color 0.3s';
    pwdInput.parentNode.appendChild(hint);

    pwdInput.addEventListener('input', () => {
      const val = pwdInput.value;
      let strength = 0;
      if (val.length >= 6)  strength++;
      if (val.length >= 10) strength++;
      if (/[A-Z]/.test(val)) strength++;
      if (/[0-9]/.test(val)) strength++;
      if (/[^A-Za-z0-9]/.test(val)) strength++;

      const labels = ['', '⚠️ Very weak', '⚠️ Weak', '✅ Fair', '✅ Strong', '💪 Very strong'];
      const colors = ['', '#ef4444', '#f97316', '#f59e0b', '#10b981', '#6366f1'];
      hint.textContent = val.length ? labels[strength] || labels[4] : '';
      hint.style.color = colors[strength] || colors[4];
    });
  }

  /* ── Cover letter expand/collapse ────────────────────────── */
  document.querySelectorAll('.cover-letter').forEach(el => {
    el.addEventListener('click', () => el.classList.toggle('expanded'));
  });

  /* ── Confirm before close / delete forms ────────────────── */
  // Already handled inline with onsubmit, but add keyboard trap on modals
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.open').forEach(m => {
        m.classList.remove('open');
      });
      closeSidebar();
    }
  });

  /* ── Modal helpers (exported to window) ─────────────────── */
  window.openModal = function (id) {
    document.getElementById(id)?.classList.add('open');
    document.body.style.overflow = 'hidden';
  };

  window.closeModal = function (id) {
    document.getElementById(id)?.classList.remove('open');
    document.body.style.overflow = '';
  };

  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
      }
    });
    overlay.querySelector('.modal-close')?.addEventListener('click', () => {
      overlay.classList.remove('open');
      document.body.style.overflow = '';
    });
  });

  /* ── Character counter for textareas ────────────────────── */
  document.querySelectorAll('textarea[maxlength]').forEach(ta => {
    const counter = document.createElement('div');
    counter.className = 'form-hint';
    counter.style.textAlign = 'right';
    ta.parentNode.appendChild(counter);
    const update = () => {
      const rem = ta.maxLength - ta.value.length;
      counter.textContent = `${rem} characters remaining`;
      counter.style.color = rem < 20 ? 'var(--warning)' : 'var(--text-muted)';
    };
    ta.addEventListener('input', update);
    update();
  });

  /* ── Scroll to top btn ───────────────────────────────────── */
  const scrollBtn = document.createElement('button');
  scrollBtn.innerHTML      = '↑';
  scrollBtn.title          = 'Back to top';
  scrollBtn.style.cssText  = `
    position:fixed; bottom:24px; right:24px; z-index:999;
    width:40px; height:40px; border-radius:50%;
    background:var(--accent-grad); color:#fff;
    border:none; cursor:pointer; font-size:1.1rem; font-weight:700;
    box-shadow:0 4px 20px rgba(99,102,241,0.4);
    opacity:0; pointer-events:none;
    transition:opacity 0.3s ease, transform 0.3s ease;
  `;
  document.body.appendChild(scrollBtn);

  window.addEventListener('scroll', () => {
    const show = window.scrollY > 300;
    scrollBtn.style.opacity       = show ? '1' : '0';
    scrollBtn.style.pointerEvents = show ? 'all' : 'none';
  });

  scrollBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  /* ── Ripple effect on buttons ────────────────────────────── */
  document.querySelectorAll('.btn').forEach(btn => {
    btn.addEventListener('click', function (e) {
      const ripple = document.createElement('span');
      const rect   = btn.getBoundingClientRect();
      const size   = Math.max(rect.width, rect.height);
      ripple.style.cssText = `
        position:absolute; border-radius:50%;
        width:${size}px; height:${size}px;
        left:${e.clientX - rect.left - size / 2}px;
        top:${e.clientY  - rect.top  - size / 2}px;
        background:rgba(255,255,255,0.25);
        transform:scale(0); animation:ripple-anim 0.6s ease-out forwards;
        pointer-events:none;
      `;
      if (getComputedStyle(btn).position === 'static') {
        btn.style.position = 'relative';
      }
      btn.style.overflow = 'hidden';
      btn.appendChild(ripple);
      setTimeout(() => ripple.remove(), 700);
    });
  });

  // Inject ripple keyframe
  if (!document.getElementById('ripple-style')) {
    const s = document.createElement('style');
    s.id = 'ripple-style';
    s.textContent = `@keyframes ripple-anim { to { transform:scale(2.5); opacity:0; } }`;
    document.head.appendChild(s);
  }

  /* ── Table row hover highlight ───────────────────────────── */
  document.querySelectorAll('.data-table tbody tr').forEach(row => {
    row.style.transition = 'background 0.15s ease';
  });

  // PWA Service Worker Registration
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('../sw.js').catch(err => console.log('SW failed:', err));
    });
  }

  /* ── Theme & Language Switcher UI ────────────────────────── */
  const configPanel = document.createElement('div');
  configPanel.style.cssText = `
    position:fixed; bottom:80px; right:24px; z-index:999;
    background:var(--bg-1); border:1px solid var(--glass-border);
    border-radius:var(--radius-md); padding:12px;
    display:flex; flex-direction:column; gap:8px;
    box-shadow:var(--shadow-lg); opacity:0; pointer-events:none;
    transition:var(--transition); transform:translateY(10px);
  `;
  document.body.appendChild(configPanel);

  const togglePanelBtn = document.createElement('button');
  togglePanelBtn.innerHTML = '⚙️';
  togglePanelBtn.style.cssText = `
    position:fixed; bottom:24px; left:24px; z-index:999;
    width:40px; height:40px; border-radius:50%;
    background:var(--bg-1); border:1px solid var(--glass-border);
    cursor:pointer; font-size:1.2rem; display:flex; align-items:center; justify-content:center;
    box-shadow:var(--shadow-md); transition:var(--transition);
  `;
  document.body.appendChild(togglePanelBtn);

  togglePanelBtn.addEventListener('click', () => {
    const isVisible = configPanel.style.opacity === '1';
    configPanel.style.opacity = isVisible ? '0' : '1';
    configPanel.style.pointerEvents = isVisible ? 'none' : 'all';
    configPanel.style.transform = isVisible ? 'translateY(10px)' : 'translateY(0)';
  });

  // Theme Toggle
  const currentTheme = localStorage.getItem('theme') || 'dark';
  document.documentElement.setAttribute('data-theme', currentTheme);
  
  const themeBtn = document.createElement('button');
  themeBtn.className = 'btn btn-ghost btn-sm';
  themeBtn.innerHTML = currentTheme === 'dark' ? '☀️ Light Mode' : '🌙 Dark Mode';
  themeBtn.onclick = () => {
    const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    themeBtn.innerHTML = newTheme === 'dark' ? '☀️ Light Mode' : '🌙 Dark Mode';
  };
  configPanel.appendChild(themeBtn);

  // Language Switcher
  const langBtn = document.createElement('button');
  langBtn.className = 'btn btn-ghost btn-sm';
  langBtn.innerHTML = '🌐 Switch Language';
  langBtn.onclick = () => {
    const currentLang = new URLSearchParams(window.location.search).get('set_lang') || 'en';
    const nextLang = currentLang === 'en' ? 'hi' : 'en';
    const url = new URL(window.location.href);
    url.searchParams.set('set_lang', nextLang);
    window.location.href = url.href;
  };
  configPanel.appendChild(langBtn);

  console.log('%c💼 JobPortal loaded', 'color:#6366f1; font-weight:bold; font-size:14px;');
})();
