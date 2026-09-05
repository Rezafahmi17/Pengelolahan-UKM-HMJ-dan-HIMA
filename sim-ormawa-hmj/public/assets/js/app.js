document.addEventListener('DOMContentLoaded', () => {

  /* ── Nav toggle ── */
  const navToggle = document.querySelector('[data-nav-toggle]');
  const nav       = document.querySelector('[data-main-nav]');
  navToggle?.addEventListener('click', () => nav?.classList.toggle('open'));
  document.addEventListener('click', (e) => {
    if (nav?.classList.contains('open') && !nav.contains(e.target) && !navToggle?.contains(e.target))
      nav.classList.remove('open');
  });

  /* ── Header scroll effect ── */
  const header = document.querySelector('.main-header');
  if (header) {
    const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 40);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ── Active nav link ── */
  const currentPath = location.pathname + location.search;
  document.querySelectorAll('.main-nav > a').forEach(link => {
    if (link.href && link.href.includes(location.hostname)) {
      const url = new URL(link.href);
      if (url.pathname + url.search === currentPath) link.classList.add('active');
    }
  });

  /* ── Scroll-reveal ── */
  const revealEls = document.querySelectorAll('.reveal');
  if (revealEls.length) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -48px 0px' });
    revealEls.forEach(el => io.observe(el));
  }

  /* ── Auto-add reveal to sections ── */
  const autoRevealTargets = [
    '.feature-card', '.service-card', '.stat-card', '.jurusan-card',
    '.directory-card', '.section-heading', '.metric-card', '.approval-card',
    '.review-card', '.person-card'
  ];
  autoRevealTargets.forEach(selector => {
    document.querySelectorAll(selector).forEach((el, i) => {
      if (!el.classList.contains('reveal')) {
        el.classList.add('reveal');
        if (i % 3 === 1) el.classList.add('reveal-delay-1');
        if (i % 3 === 2) el.classList.add('reveal-delay-2');
      }
    });
  });
  // re-observe newly added
  document.querySelectorAll('.reveal').forEach(el => {
    const io2 = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) { entry.target.classList.add('visible'); io2.unobserve(entry.target); }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
    if (!el.classList.contains('visible')) io2.observe(el);
  });

  /* ── Animated counter for stat-cards ── */
  const counterEls = document.querySelectorAll('.stat-card strong, .metric-card strong');
  const animateCounter = (el) => {
    const target = parseInt(el.textContent.replace(/\D/g,''), 10);
    if (isNaN(target) || target === 0) return;
    const duration = 900;
    const step     = 16;
    const increment = target / (duration / step);
    let current = 0;
    const timer = setInterval(() => {
      current += increment;
      if (current >= target) { current = target; clearInterval(timer); }
      el.textContent = Math.floor(current).toLocaleString('id-ID');
    }, step);
  };
  if (counterEls.length) {
    const counterIo = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          counterIo.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });
    counterEls.forEach(el => counterIo.observe(el));
  }

  /* ── Subtle parallax on hero-bg ── */
  const heroBg = document.querySelector('.hero-bg');
  if (heroBg) {
    heroBg.classList.add('loaded'); // trigger scale animation
    window.addEventListener('scroll', () => {
      const y = window.scrollY;
      if (y < window.innerHeight * 1.5)
        heroBg.style.transform = `translateY(${y * 0.25}px)`;
    }, { passive: true });
  }

  /* ── Toast auto-dismiss ── */
  document.querySelectorAll('[data-toast]').forEach(toast => {
    setTimeout(() => {
      toast.style.opacity    = '0';
      toast.style.transform  = 'translateY(-12px) scale(.96)';
      toast.style.transition = 'opacity .4s, transform .4s';
    }, 3800);
    setTimeout(() => toast.remove(), 4300);
  });

  /* ── Confirm dialogs ── */
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', ev => {
      if (!window.confirm(el.dataset.confirm || 'Lanjutkan aksi ini?')) ev.preventDefault();
    });
  });

  /* ── Dialogs (open/close) ── */
  document.querySelectorAll('[data-open-dialog]').forEach(btn => {
    btn.addEventListener('click', () => document.getElementById(btn.dataset.openDialog)?.showModal());
  });
  document.querySelectorAll('[data-close-dialog]').forEach(btn => {
    btn.addEventListener('click', () => btn.closest('dialog')?.close());
  });
  document.querySelectorAll('dialog').forEach(dialog => {
    dialog.addEventListener('click', ev => { if (ev.target === dialog) dialog.close(); });
  });

  /* ── Password toggle ── */
  document.querySelector('[data-toggle-password]')?.addEventListener('click', ev => {
    const input = document.querySelector('[data-password]');
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
    ev.currentTarget.textContent = input.type === 'password' ? 'Lihat' : 'Sembunyi';
  });

  /* ── Table search ── */
  const search = document.querySelector('[data-table-search]');
  const table  = document.querySelector('[data-search-table]');
  if (search && table) {
    search.addEventListener('input', () => {
      const q = search.value.toLowerCase().trim();
      table.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }

  /* ── Tabs ── */
  document.querySelectorAll('[data-tabs]').forEach(tabRoot => {
    tabRoot.querySelectorAll('[data-tab]').forEach(btn => {
      btn.addEventListener('click', () => {
        const key = btn.dataset.tab;
        tabRoot.querySelectorAll('[data-tab]').forEach(x => x.classList.toggle('active', x === btn));
        document.querySelectorAll('[data-tab-panel]').forEach(panel =>
          panel.classList.toggle('active', panel.dataset.tabPanel === key)
        );
      });
    });
  });

  /* ── Fill dialog helper ── */
  function fillDialog(dialogId, data, map = {}) {
    const dialog = document.getElementById(dialogId);
    if (!dialog) return;
    Object.entries(data).forEach(([key, value]) => {
      const name  = map[key] || key;
      const field = dialog.querySelector(`[name="${CSS.escape(name)}"]`);
      if (field) field.value = value ?? '';
    });
    dialog.showModal();
  }

  document.querySelectorAll('[data-edit-member]').forEach(btn   => btn.addEventListener('click', () => fillDialog('member-dialog',  JSON.parse(btn.dataset.editMember))));
  document.querySelectorAll('[data-edit-program]').forEach(btn  => btn.addEventListener('click', () => fillDialog('program-dialog', JSON.parse(btn.dataset.editProgram))));
  document.querySelectorAll('[data-edit-asset]').forEach(btn    => btn.addEventListener('click', () => fillDialog('asset-dialog',   JSON.parse(btn.dataset.editAsset))));
  document.querySelectorAll('[data-edit-finance]').forEach(btn  => btn.addEventListener('click', () => fillDialog('finance-dialog', JSON.parse(btn.dataset.editFinance))));

  document.querySelectorAll('[data-compose-to]').forEach(btn => {
    btn.addEventListener('click', () => {
      const dialog   = document.getElementById('message-dialog');
      if (!dialog) return;
      const receiver = dialog.querySelector('[name="receiver_tenant_id"]');
      if (receiver) receiver.value = btn.dataset.composeTo;
      dialog.showModal();
    });
  });

  /* ── Button ripple effect ── */
  document.querySelectorAll('.btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      const ripple = document.createElement('span');
      const rect   = this.getBoundingClientRect();
      const size   = Math.max(rect.width, rect.height) * 2;
      ripple.style.cssText = `
        position:absolute; border-radius:50%;
        width:${size}px; height:${size}px;
        left:${e.clientX - rect.left - size/2}px;
        top:${e.clientY - rect.top - size/2}px;
        background:rgba(255,255,255,.25);
        transform:scale(0);
        animation:rippleEffect .5s ease-out forwards;
        pointer-events:none;
      `;
      if (!document.getElementById('ripple-style')) {
        const style = document.createElement('style');
        style.id = 'ripple-style';
        style.textContent = '@keyframes rippleEffect{to{transform:scale(1);opacity:0}}';
        document.head.appendChild(style);
      }
      this.appendChild(ripple);
      setTimeout(() => ripple.remove(), 520);
    });
  });

});
