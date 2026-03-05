jQuery(function($){

function loadFields(){

 let form_id = $('#fwc_form_id').val();
 if(!form_id) return;

 $.post(FWC.ajax,{
    action:'fwc_get_fields',
    form_id,
    _ajax_nonce:FWC.nonce
 }, function(res){

    if(!res.success) return;

    $('.fwc-field').each(function(){

        let select = $(this);
        let sel = select.attr('data-selected');

        select.empty().append('<option value="">— Select Field —</option>');

        $.each(res.data,function(i,f){

            let label = f.label + ' — {' + f.id + '}';

            let o = $('<option/>')
                .val(f.key)
                .text(label);

            if(sel === f.key) o.prop('selected',true);

            select.append(o);
        });

    });
 });

}

$('#fwc_form_id').on('change',loadFields);

loadFields();

});
