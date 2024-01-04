jQuery.entwine("handsontablefield", function ($)
{
    $(".handsontablefield").entwine({
        onmatch: function ()
        {
            const hotElement = $(this).find('.js-handsontablefield');
            const hotTextarea = $(this).find('textarea');
            const hotOptionsRaw = hotElement.attr('data-handsontable-options');
            const hotOptions = JSON.parse(hotOptionsRaw);
            hotOptions.afterChange = function(change, source) {
                if (source === 'loadData') return;
                const latestData = JSON.stringify(hot.getData());
                hotTextarea.val(latestData);
            };
            if (hotOptions.firstRowHeader) {
                hotOptions.rowHeaders = function(index) {
                    return index === 0 ? hotOptions.firstRowHeader : index;
                }
            }
            console.log(hotOptions);
            const hot = new Handsontable(hotElement.get(0), hotOptions);
        }
    });
});
