/* =========================================================================
 * Guessing (کلاغ‌پر) client — vanilla ES2017, strictly scoped to the game
 * pages (bootstrapped via window.__KALAQPAR__ set by the module views).
 * No framework, no build step, DOM-safe: user strings are rendered with
 * textContent only, never innerHTML.
 * ========================================================================= */
(function () {
    'use strict';

    var boot = window.__KALAQPAR__;
    if (!boot) {
        return;
    }

    var API = '/kalaqpar';
    var state = { version: boot.initialVersion || 0, round: null, room: null };
    var guessLockUntil = 0;
    var pollTimer = null;

    /* ------------------------------------------------------------------ *
     * helpers                                                            *
     * ------------------------------------------------------------------ */

    function $(id) {
        return document.getElementById(id);
    }

    function digits(x) {
        var fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return String(x).replace(/\d/g, function (d) { return fa[+d]; });
    }

    function toast(message, kind) {
        var wrap = document.querySelector('.klq-toast-wrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'klq-toast-wrap';
            document.body.appendChild(wrap);
        }
        var node = document.createElement('div');
        node.className = 'klq-toast' + (kind ? ' is-' + kind : '');
        node.setAttribute('role', 'status');
        node.textContent = message;
        wrap.appendChild(node);
        setTimeout(function () {
            node.remove();
        }, 3600);
    }

    function post(path, body) {
        return fetch(API + path, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
                'X-Guessing-CSRF': boot.csrf
            },
            credentials: 'same-origin',
            body: JSON.stringify(body || {})
        }).then(function (response) {
            return response.json().catch(function () {
                throw window.GuessingUnknownError || { error: 'network_error', message: 'اتصال به سرور برقرار نشد.' };
            }).then(function (data) {
                if (!response.ok || data.ok === false) {
                    throw data;
                }
                return data;
            });
        });
    }

    /* ------------------------------------------------------------------ *
     * lobby                                                              *
     * ------------------------------------------------------------------ */

    function renderRoomRow(room) {
        var row = document.createElement('div');
        row.className = 'klq-room-row';

        var main = document.createElement('div');
        var name = document.createElement('div');
        name.className = 'klq-room-name';
        name.textContent = room.name || 'اتاق بدون نام';
        var meta = document.createElement('div');
        meta.className = 'klq-room-meta';
        meta.textContent = 'کد ' + room.code + ' · ' +
            (room.status === 'playing'
                ? 'در بازی (دور ' + digits(room.current_round_number) + '/' + digits(room.rounds_total) + ')'
                : 'در اتاق انتظار') +
            ' · بازیکنان: ' + digits(room.players);
        main.appendChild(name);
        main.appendChild(meta);

        var action = document.createElement('a');
        action.className = 'btn btn-sm klq-btn';
        action.href = API + '/room/' + encodeURIComponent(room.code);
        action.textContent = room.status === 'closed' ? 'بسته' : boot.strings.join;

        row.appendChild(main);
        row.appendChild(action);
        return row;
    }

    function bindLobby() {
        var list = $('klq-rooms');
        if (!list) {
            return;
        }

        var rooms = [];
        try {
            rooms = JSON.parse(list.getAttribute('data-rooms') || '[]');
        } catch (error) {
            rooms = [];
        }

        if (rooms.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'klq-rooms-empty mb-0';
            empty.textContent = boot.strings.empty || 'هنوز اتاقی باز نشده.';
            list.appendChild(empty);
        } else {
            var seen = {};
            rooms.forEach(function (room) {
                if (room && room.code && !seen[room.code] && room.status !== 'closed') {
                    seen[room.code] = true;
                    list.appendChild(renderRoomRow(room));
                }
            });
        }

        // Keep the open-room list fresh for players with the lobby open.
        setInterval(function () {
            fetch(API + '/rooms', { credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        return;
                    }
                    list.textContent = '';
                    var seenNow = {};
                    data.rooms.forEach(function (room) {
                        if (room.status === 'closed' || seenNow[room.code]) {
                            return;
                        }
                        seenNow[room.code] = true;
                        list.appendChild(renderRoomRow(room));
                    });
                })
                .catch(function () { /* transient */ });
        }, 10000);

        var profileForm = $('klq-profile-form');
        if (profileForm) {
            profileForm.addEventListener('submit', function (event) {
                event.preventDefault();
                post('/profile', { display_name: $('klq-profile-name').value })
                    .then(function (data) { toast('نام «' + data.display_name + '» ذخیره شد.', 'good'); })
                    .catch(function (error) { toast(error.message || 'نام ذخیره نشد.', 'error'); });
            });
        }

        var createForm = $('klq-create-form');
        if (createForm) {
            createForm.addEventListener('submit', function (event) {
                event.preventDefault();
                var name = $('klq-create-name').value;
                var rounds = $('klq-create-rounds').value;
                post('/rooms', { name: name, rounds: parseInt(rounds, 10) })
                    .then(function (data) {
                        window.location.href = API + '/room/' + encodeURIComponent(data.room);
                    })
                    .catch(function (error) { toast(error.message || 'اتاق ساخته نشد.', 'error'); });
            });
        }

        var joinForm = $('klq-join-form');
        if (joinForm) {
            joinForm.addEventListener('submit', function (event) {
                event.preventDefault();
                var code = $('klq-join-code').value.trim().toUpperCase();
                if (code.length !== 6) {
                    toast('کد اتاق باید ۶ حرف باشد.', 'error');
                    return;
                }
                post('/rooms/join', { room: code })
                    .then(function () {
                        window.location.href = API + '/room/' + encodeURIComponent(code);
                    })
                    .catch(function (error) { toast(error.message || 'پیوستن ناموفق بود.', 'error'); });
            });
        }
    }

    /* ------------------------------------------------------------------ *
     * game board                                                         *
     * ------------------------------------------------------------------ */

    function renderWord(round) {
        var box = $('klq-word');
        if (!box) {
            return;
        }
        box.textContent = '';

        var shown = (round && round.revealed) ? round.revealed : [];
        var length = round ? Math.max(round.word_length, shown.length, 1) : 0;

        for (var i = 0; i < length; i++) {
            var slot = document.createElement('span');
            slot.className = 'klq-letter';
            var letter = shown[i];
            if (letter) {
                slot.classList.add('is-revealed');
                slot.textContent = letter;
            } else {
                slot.classList.add('is-empty');
                slot.textContent = 'ا'; // keeps RTL shaping of hidden slots
            }
            box.appendChild(slot);
        }

        if (!round && box.textContent === '') {
            var empty = document.createElement('span');
            empty.className = 'klq-letter is-empty';
            box.appendChild(empty);
        }
    }

    function renderPlayers(room, players) {
        var list = $('klq-players');
        if (!list) {
            return;
        }
        list.textContent = '';

        players.forEach(function (player) {
            var item = document.createElement('li');
            item.className = 'klq-player';
            if (player.guest_id === room.host_guest_id) {
                item.classList.add('is-host');
            }

            var label = document.createElement('span');
            var dot = document.createElement('span');
            dot.className = 'klq-online' + (player.online ? ' is-on' : '');
            label.appendChild(dot);
            label.appendChild(document.createTextNode(' ' + player.display_name));

            var score = document.createElement('span');
            score.className = 'klq-score';
            score.textContent = '';

            item.appendChild(label);
            item.appendChild(score);
            list.appendChild(item);
        });

        var startBtn = $('klq-start-btn');
        if (startBtn) {
            var isHost = boot.me === room.host_guest_id;
            startBtn.disabled = !(isHost && room.status === 'lobby' && players.length >= 2);
            startBtn.textContent = room.status === 'lobby'
                ? (players.length < 2 ? 'شروع بازی (حداقل ۲ بازیکن)' : 'شروع بازی')
                : 'بازی در جریان است';
        }

        var hint = $('klq-join-hint');
        if (hint) {
            var meIsMember = players.some(function (player) {
                return player.guest_id === boot.me;
            });
            hint.textContent = meIsMember
                ? 'کد بالا را برای دعوت دوستان بفرست.'
                : (room.status === 'closed' ? boot.strings.closed :
                    'برای بازی به این اتاق پیوسته‌ای؟ دکمه‌ی «پیوستن» در صفحه‌ی اصلی با کد اتاق کار می‌کند.');
        }
    }

    function renderRoomScores() {
        var box = $('klq-room-scores');
        if (!box) {
            return;
        }

        var scores = state.scores || [];

        if (scores.length === 0) {
            box.textContent = '';
            var li = document.createElement('li');
            li.className = 'text-secondary';
            li.textContent = 'هنوز امتیازی در این اتاق نیست.';
            box.appendChild(li);
            return;
        }

        box.textContent = '';
        scores.forEach(function (entry) {
            var item = document.createElement('li');
            var name = document.createElement('span');
            name.textContent = entry.display_name;
            var points = document.createElement('b');
            points.textContent = digits(entry.total);
            item.appendChild(name);
            item.appendChild(points);
            box.appendChild(item);
        });
    }

    function statusMessage(room, round) {
        var statusBox = $('klq-status');
        if (!statusBox) {
            return;
        }

        if (room.status === 'closed') {
            statusBox.textContent = boot.strings.closed;
            return;
        }

        if (!round) {
            statusBox.textContent = room.status === 'lobby'
                ? 'اتاق انتظار: میزبان وقتی دو بازیکن اینجا باشند بازی را شروع می‌کند.'
                : boot.strings.waiting;
            return;
        }

        if (round.status === 'running') {
            statusBox.textContent = 'کلمه را حدس بزن! حرف‌های پنهان مثل پرهای کلاغ ته می‌مانند.';
            return;
        }

        statusBox.textContent = boot.strings.waiting;
    }

    function renderTimer(round, room) {
        var timer = $('klq-timer');
        var meta = $('klq-round-number');
        var reveals = $('klq-reveals');
        var budget = $('klq-guess-budget');

        if (!timer || !room) {
            return;
        }

        clearInterval(clock);

        var clock = setInterval(function () {
            var current = state.round;
            if (!current || current.status !== 'running') {
                timer.textContent = '—';
                return;
            }
            var secondsLeft = Math.max(0, Math.floor((current.ends_at * 1000 - Date.now()) / 1000));
            timer.textContent = digits(Math.floor(secondsLeft / 60)) + ':' + digits(String(secondsLeft % 60).padStart(2, '0'));
        }, 500);

        if (meta) {
            meta.textContent = room.round_number ? digits(room.round_number) : '—';
        }
        if (reveals && state.round) {
            reveals.textContent = 'حرف‌های پیدا شده: ' + digits(state.round.revealed_count) + ' / ' + digits(state.round.word_length);
        }
        if (budget && state.round) {
            budget.textContent = 'حدس‌های باقی‌مانده: ' + digits(state.round.guesses_left);
        }
    }

    function guessEnabled(room, round) {
        var input = $('klq-guess-input');
        var button = $('klq-guess-btn');
        if (!input || !button) {
            return;
        }

        var enabled = !!round && round.status === 'running'
            && round.guesses_left > 0
            && room.status === 'playing'
            && Date.now() > guessLockUntil
            && round.cooldown_left === 0;

        input.disabled = !enabled;
        button.disabled = !enabled;
    }

    /* ------------------------------------------------------------------ *
     * event stream                                                       *
     * ------------------------------------------------------------------ */

    function applyEvents(events) {
        events.forEach(function (event) {
            var payload = event.payload || {};
            switch (event.type) {
                case 'letter_revealed':
                    if (state.round && payload.revealed) {
                        state.round.revealed = payload.revealed;
                        state.round.revealed_count = payload.revealed_count;
                    }
                    break;
                case 'round_finished':
                    if (payload.word) {
                        showSolvedWord(payload);
                    }
                    break;
                case 'room_closed':
                    state.room && (state.room.status = 'closed');
                    break;
            }
        });
    }

    function showSolvedWord(payload) {
        var box = $('klq-word');
        var statusBox = $('klq-status');
        if (!box) {
            return;
        }
        box.textContent = '';
        String(payload.word).split('').forEach(function (letter) {
            var slot = document.createElement('span');
            slot.className = 'klq-letter is-revealed';
            slot.textContent = letter;
            box.appendChild(slot);
        });
        if (statusBox && payload.winner) {
            statusBox.textContent = (payload.winner.display_name || 'یک بازیکن') +
                ' کلمه را ساخت (+' + digits(payload.winner.points) + ' امتیاز).';
        } else if (statusBox) {
            statusBox.textContent = 'زمان تمام شد؛ کلمه «' + payload.word + '» بود.';
        }
    }

    /* ------------------------------------------------------------------ *
     * polling                                                            *
     * ------------------------------------------------------------------ */

    function schedulePoll(delay) {
        clearTimeout(pollTimer);
        pollTimer = setTimeout(pollState, delay == null ? 1200 : delay);
    }

    function pollState() {
        if (document.hidden) {
            schedulePoll(5000);
            return;
        }

        fetch(API + '/state?room=' + encodeURIComponent(boot.roomCode) +
              '&after=' + encodeURIComponent(state.version), {
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.ok) {
                    schedulePoll(4000);
                    return;
                }

                applyEvents(data.events);

                if (data.room) {
                    state.room = data.room;
                }
                state.round = data.round;

                applyStateRoom(data);
                schedulePoll(1200);
            })
            .catch(function () {
                schedulePoll(4000);
            });
    }

    function applyStateRoom(data) {
        var room = data.room;
        var round = data.round;

        renderWord(round);
        statusMessage(room, round);
        renderPlayers(room, data.players || []);
        renderTimer(round, room);

        state.scores = data.scores || [];
        renderRoomScores();

        guessEnabled(room, round);
    }

    /* ------------------------------------------------------------------ *
     * composer                                                           *
     * ------------------------------------------------------------------ */

    function bindComposer() {
        var form = $('klq-guess-form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var input = $('klq-guess-input');
            var guess = input.value.trim();
            if (guess === '') {
                return;
            }

            var button = $('klq-guess-btn');
            button.disabled = true;
            guessLockUntil = Date.now() + 2000;

            post('/guess', { room: boot.roomCode, guess: guess })
                .then(function (data) {
                    input.value = '';
                    if (data.correct) {
                        toast(boot.strings.correct, 'good');
                    }
                })
                .catch(function (error) {
                    if (error.error === 'rate_limited') {
                        guessLockUntil = Date.now() + 1000 * (error.retry_after || 2);
                    }
                    toast(error.message || 'حدس ثبت نشد.', 'error');
                })
                .then(function () {
                    pollState();
                });
        });
    }

    /* ------------------------------------------------------------------ *
     * host / membership controls                                         *
     * ------------------------------------------------------------------ */

    function bindControls() {
        var startBtn = $('klq-start-btn');
        if (startBtn) {
            startBtn.addEventListener('click', function () {
                post('/rooms/start', { room: boot.roomCode })
                    .then(function () {
                        toast(boot.strings.roundStarted, 'good');
                        pollState();
                    })
                    .catch(function (error) { toast(error.message || 'شروع ناموفق بود.', 'error'); });
            });
        }

        var leaveBtn = $('klq-leave-btn');
        if (leaveBtn) {
            leaveBtn.addEventListener('click', function () {
                post('/rooms/leave', { room: boot.roomCode })
                    .then(function () {
                        window.location.href = API;
                    })
                    .catch(function (error) { toast(error.message || 'ترک اتاق ناموفق بود.', 'error'); });
            });
        }

        // CopyRoomCode via badge
        var badge = $('klq-room-code');
        if (badge && navigator.clipboard) {
            badge.addEventListener('click', function () {
                navigator.clipboard.writeText(badge.textContent.trim())
                    .then(function () {
                        toast('کد اتاق کپی شد.', 'good');
                    })
                    .catch(function () {});
            });
        }
    }

    /* ------------------------------------------------------------------ */

    document.addEventListener('DOMContentLoaded', function () {
        if (boot.mode === 'lobby') {
            bindLobby();
            return;
        }

        bindControls();
        bindComposer();
        pollState();
    });
})();
