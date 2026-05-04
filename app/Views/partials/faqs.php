<?php if (!empty($faqs)): ?>
    <section class="section">
        <div class="container narrow">
            <div class="section-kicker">FAQs</div>
            <h2>Common Questions</h2>
            <div class="faq-list">
                <?php foreach ($faqs as $faq): ?>
                    <details>
                        <summary><?= e($faq['question']) ?></summary>
                        <div><?= safe_html($faq['answer']) ?></div>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
