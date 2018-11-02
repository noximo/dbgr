function debugToggle(element, event) {
    if (event.detail === 3 || (event.ctrlKey && event.detail === 2)) {
        if (element.nextElementSibling.style.display === 'none') {
            element.nextElementSibling.style.display = 'block';
        } else {
            element.nextElementSibling.style.display = 'none';
        }
        unselectText();
    }
}

function debugExpand(element, event) {
    if (event.detail === 3 || (event.ctrlKey && event.detail === 2)) {

        if (element.style.maxHeight === '1000px') {
            element.style.maxHeight = '100px';
        } else {
            element.style.maxHeight = '1000px';
        }
        unselectText();
    }
}

function unselectText() {
    window.getSelection().removeAllRanges();
}

Tracy.Dumper.init();
