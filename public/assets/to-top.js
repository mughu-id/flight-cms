const button = document.querySelector('.to-top');
if (button) {
    const toggle = () => {
        button.hidden = window.scrollY < 500;
    };
    window.addEventListener('scroll', toggle, { passive: true });
    button.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    toggle();
}

document.querySelectorAll('.cms-content h2').forEach((heading) => {
    if (!/faq/i.test(heading.textContent.trim())) return;
    const list = document.createElement('div');
    list.className = 'faq-list';
    let node = heading.nextElementSibling;
    while (node && node.tagName !== 'H2') {
        if (node.tagName === 'H3') {
            const question = node;
            const answer = question.nextElementSibling;
            const next = answer && answer.tagName === 'P' ? answer.nextElementSibling : question.nextElementSibling;
            const item = document.createElement('div');
            item.className = 'faq-item';
            item.appendChild(question);
            if (answer && answer.tagName === 'P') item.appendChild(answer);
            list.appendChild(item);
            node = next;
            continue;
        }
        const next = node.nextElementSibling;
        list.appendChild(node);
        node = next;
    }
    if (list.childNodes.length) heading.after(list);
});
