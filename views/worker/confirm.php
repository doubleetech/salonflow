<div class="app-shell">
    <header class="topbar">
        <div class="topbar__left">
            <div class="topbar__brand"><?php echo APP_NAME; ?> · Staff</div>
        </div>
        <div class="topbar__user">
            <span><i class="fas fa-user-cog"></i> <?php echo htmlspecialchars(Session::get('user_name')); ?></span>
            <a class="link-muted" href="<?php echo APP_URL; ?>/index.php?route=logout">
                <i class="fas fa-sign-out-alt"></i> Log Out
            </a>
        </div>
    </header>

    <main class="content">
        <style>
            .confirm-wrap { max-width: 460px; margin: 0 auto; padding: 8px 0 32px; }
            .confirm-count { text-align: center; font-weight: 700; font-size: 1.05rem; margin: 8px 0 16px; }
            .confirm-card { background: #fff; border: 1px solid #d9e2f2; border-radius: 16px; padding: 22px 20px; box-shadow: 0 6px 22px rgba(20, 50, 120, .10); }
            .confirm-tag { display: inline-block; font-size: .72rem; letter-spacing: .08em; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: #e8f0ff; color: #1d4ed8; }
            .confirm-tag--expired { background: #fdeaea; color: #b42318; }
            .confirm-amount { font-size: 2.2rem; font-weight: 700; margin: 14px 0 2px; }
            .confirm-line { color: #475467; margin: 2px 0; }
            .confirm-meta { color: #667085; font-size: .9rem; margin-top: 12px; }
            .confirm-note { margin-top: 14px; padding: 10px 12px; background: #f5f8ff; border-radius: 10px; color: #344054; }
            .confirm-actions { display: grid; gap: 12px; margin-top: 20px; }
            .confirm-actions--two { grid-template-columns: 1fr 1fr; }
            .confirm-btn { border: 0; border-radius: 12px; padding: 18px 10px; font-size: 1.05rem; font-weight: 700; cursor: pointer; letter-spacing: .03em; font-family: inherit; }
            .confirm-btn:disabled { opacity: .55; cursor: wait; }
            .confirm-btn--accept { background: #1d4ed8; color: #fff; }
            .confirm-btn--appeal { background: #fff; color: #b42318; border: 2px solid #b42318; }
            .confirm-btn--reason { background: #fff; color: #1d4ed8; border: 2px solid #1d4ed8; }
            .confirm-title { text-align: center; font-weight: 700; margin: 4px 0 2px; }
            .confirm-back { display: block; text-align: center; margin-top: 14px; color: #667085; font-size: .9rem; background: none; border: 0; cursor: pointer; font-family: inherit; }
            .confirm-error { margin-top: 12px; padding: 10px 12px; border-radius: 10px; background: #fdeaea; color: #b42318; font-size: .92rem; }
            .confirm-update { margin-top: 14px; padding: 10px 12px; background: #fff7e6; border-left: 4px solid #f79009; border-radius: 8px; color: #54380a; font-size: .95rem; }
            .confirm-update strong { display: block; font-size: .78rem; letter-spacing: .06em; text-transform: uppercase; margin-bottom: 2px; }
            .confirm-was { color: #667085; font-size: .92rem; text-decoration: line-through; margin-top: 2px; }
            .confirm-hint { text-align: center; color: #667085; font-size: .88rem; margin-top: 10px; }
        </style>

        <div class="confirm-wrap">
            <div class="confirm-count" id="confirmCount"></div>
            <div class="confirm-card" id="confirmCard"></div>
            <input type="hidden" id="confirmCsrf" value="<?php echo htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <script>
        (function () {
            var appUrl = <?php echo json_encode(APP_URL); ?>;
            var card = <?php echo json_encode($card, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            var csrf = document.getElementById('confirmCsrf').value;
            var cardEl = document.getElementById('confirmCard');
            var countEl = document.getElementById('confirmCount');
            var busy = false;

            function el(tag, cls, text) {
                var e = document.createElement(tag);
                if (cls) e.className = cls;
                if (text !== undefined && text !== null) e.textContent = text;
                return e;
            }

            function button(label, cls, onClick) {
                var b = el('button', 'confirm-btn ' + cls, label);
                b.type = 'button';
                b.addEventListener('click', onClick);
                return b;
            }

            function showRecord(c, errorMsg) {
                card = c;
                countEl.textContent = c.remaining + (c.remaining === 1 ? ' Record Requires Confirmation' : ' Records Require Confirmation');
                cardEl.innerHTML = '';

                var tag = 'NEW RECORD';
                if (c.expired) tag = 'APPEAL WINDOW CLOSED';
                else if (c.revision_note) tag = c.appealed_before ? 'RESPONSE TO YOUR APPEAL' : 'UPDATED RECORD';
                cardEl.appendChild(el('span', 'confirm-tag' + (c.expired ? ' confirm-tag--expired' : ''), tag));
                cardEl.appendChild(el('div', 'confirm-amount', c.amount));
                if (c.old_amount) cardEl.appendChild(el('div', 'confirm-was', 'Was ' + c.old_amount));
                if (c.tip) cardEl.appendChild(el('div', 'confirm-line', 'Cashback: ' + c.tip));
                if (c.note) cardEl.appendChild(el('div', 'confirm-note', c.note));
                if (c.revision_note) {
                    var upd = el('div', 'confirm-update');
                    upd.appendChild(el('strong', null, 'Update from ' + (c.revised_by || 'your cashier')));
                    upd.appendChild(document.createTextNode(c.revision_note));
                    cardEl.appendChild(upd);
                }
                cardEl.appendChild(el('div', 'confirm-meta', 'Added by ' + c.cashier + ' · ' + c.time));
                if (errorMsg) cardEl.appendChild(el('div', 'confirm-error', errorMsg));

                var actions = el('div', 'confirm-actions' + (c.can_appeal ? ' confirm-actions--two' : ''));
                actions.appendChild(button('ACCEPT', 'confirm-btn--accept', accept));
                if (c.can_appeal) actions.appendChild(button('APPEAL', 'confirm-btn--appeal', showAppeal));
                cardEl.appendChild(actions);

                if (c.expired) cardEl.appendChild(el('div', 'confirm-hint', 'The time to appeal this record has passed. Accept it to continue.'));
                else if (!c.can_appeal) cardEl.appendChild(el('div', 'confirm-hint', 'You have used your appeals for this record. Accept it to continue.'));
            }

            function showAppeal() {
                if (busy) return;
                cardEl.innerHTML = '';
                cardEl.appendChild(el('div', 'confirm-title', 'APPEAL RECORD'));
                cardEl.appendChild(el('div', 'confirm-meta', card.amount + (card.note ? ' · ' + card.note : '')));
                var actions = el('div', 'confirm-actions');
                actions.appendChild(button('Wrong amount', 'confirm-btn--reason', function () { appeal('wrong_amount'); }));
                actions.appendChild(button('Other', 'confirm-btn--reason', function () { appeal('other'); }));
                cardEl.appendChild(actions);
                var back = el('button', 'confirm-back', '← Back');
                back.type = 'button';
                back.addEventListener('click', function () { if (!busy) showRecord(card); });
                cardEl.appendChild(back);
            }

            function setBusy(state) {
                busy = state;
                var buttons = cardEl.querySelectorAll('button');
                for (var i = 0; i < buttons.length; i++) buttons[i].disabled = state;
            }

            function toast(msg, goingToDashboard) {
                if (goingToDashboard) {
                    try { window.sessionStorage.setItem('sfToast', msg); } catch (e) {}
                } else if (window.sfToast) {
                    window.sfToast(msg);
                }
            }

            function send(path, fields, doneMessage) {
                if (busy) return;
                setBusy(true);
                var fd = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('record_id', card.id);
                for (var k in fields) fd.append(k, fields[k]);

                fetch(appUrl + '/index.php?route=' + path, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    busy = false;
                    if (data.success) { toast(doneMessage, data.done); }
                    if (data.done) {
                        window.location.href = data.redirect;
                        return;
                    }
                    showRecord(data.card, data.success ? null : data.error);
                })
                .catch(function () {
                    setBusy(false);
                    showRecord(card, 'Something went wrong. Check your connection and try again.');
                });
            }

            function accept() { send('worker/confirm/accept', {}, 'Record accepted \u2713'); }
            function appeal(reason) { send('worker/confirm/appeal', { reason: reason }, 'Appeal sent \u2713'); }

            showRecord(card);
        })();
        </script>
    </main>
</div>
