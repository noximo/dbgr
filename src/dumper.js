function debugToggle(element, event) {
    if (!event.ctrlKey) {
        return;
    }
    if (element.nextElementSibling.style.display === 'none') {
        element.nextElementSibling.style.display = 'block';
    } else {
        element.nextElementSibling.style.display = 'none';
    }
}

function debugExpand(element, event) {
    if (!event.ctrlKey) {
        return;
    }
    if (element.style.maxHeight === '1000px') {
        element.style.maxHeight = '100px';
    } else {
        element.style.maxHeight = '1000px';
    }

}

Tracy.Dumper.init();
