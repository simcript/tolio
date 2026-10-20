<?php $this->start('content') ?>

<div class="taskbar">
    <div class="tb-brand">
        <span style="font-size:22px">🧮</span>
        <div>
            <div class="name">Calma</div>
            <div class="sub">Multi-instance</div>
        </div>
    </div>
    <div class="tb-sep"></div>
    <button class="tb-btn tb-add" onclick="CalcManager.add()">
        <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
            <path d="M6.5 1v11M1 6.5h11" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
        </svg>
        New
    </button>
    <button class="tb-btn tb-wipe" onclick="CalcManager.closeAll()">
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
            <path d="M1 1l10 10M11 1L1 11" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        Close All
    </button>
    <div class="tb-count">Open: <b id="globalCounter">0</b></div>
</div>

<div class="desktop" id="desktop">
    <div class="empty" id="emptyState">
        <span class="empty-ico">🧮</span>
        <h2>No calculators open</h2>
        <p>Press "New" to add one</p>
    </div>
</div>

<?php $this->end() ?>
