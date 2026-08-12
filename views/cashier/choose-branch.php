<div class="auth-screen">
    <div class="auth-card auth-card--wide">
        <div class="brand">
            <h1><?php echo APP_NAME; ?></h1>
            <p class="brand-sub">Which branch are you working today?</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (empty($branches)): ?>
            <div class="alert alert--error">No active branches are set up yet. Ask your Admin to add one.</div>
        <?php else: ?>
        <form method="POST" action="<?php echo APP_URL; ?>/index.php?route=cashier/choose-branch">
            <?php echo Csrf::field(); ?>

            <label for="branch_id">Branch</label>
            <select id="branch_id" name="branch_id" required autofocus>
                <option value="">-- Select Branch --</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="field-hint">This locks in for the whole business day — you won't be able to switch until it's closed.</p>

            <button type="submit" class="btn btn--primary">Start My Day</button>
        </form>
        <?php endif; ?>
    </div>
</div>
