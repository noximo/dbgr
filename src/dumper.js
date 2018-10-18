function debugHide(element) {
    if (element.style.display === 'none') {
        element.style.display = 'block';
    } else {
        element.style.display = 'none';
    }
    unselectText();
}

function debugToggle(element) {
    if (element.nextElementSibling.style.display === 'none') {
        element.nextElementSibling.style.display = 'block';
    } else {
        element.nextElementSibling.style.display = 'none';
    }
    unselectText();
}

function debugExpand(element) {
    if (element.style.maxHeight === '1000px') {
        element.style.maxHeight = '100px';
    } else {
        element.style.maxHeight = '1000px';
    }
    unselectText();
}

function unselectText() {
    window.getSelection().removeAllRanges();
}

