jQuery(document).ready(function($){
    initButtons = function(){$('.ioa-proceed').click(function(e){
        e.preventDefault();
        if( $(e.currentTarget).hasClass('disabled')) return;
        $(e.currentTarget).prop('disabled', true).addClass('disabled');
        $(e.currentTarget).closest('tr').find('.spinner').css('visibility', 'visible');
      
        var action = $(e.currentTarget).data('action');
        var file = $(e.currentTarget).data('file');
        $.ajax({
            url: ajaxurl, 
            data: { action : action, file : file}, 
            type: 'post',
            dataType: 'json',        
            success: function(response) {
                $(e.currentTarget).prop('disabled',false).removeClass('disabled');
                $(e.currentTarget).closest('tr').find('.spinner').css('visibility', 'hidden');
               // response = $.parseJSON(response);                
                if(typeof(response.status)!=='undefined' && response.status===true){
                    if(action==='wpio_optimize'){
                        $(e.currentTarget).parents('tr').find('.optimizationStatus').html( response.datas.msg);
                        $(e.currentTarget).replaceWith('<a class="button ioa-proceed" data-action="wpio_revert" data-file="'+file+'"></span>Revert to original</a>');
                    }else{
                        $(e.currentTarget).parents('tr').find('.optimizationStatus').html('');
                        $(e.currentTarget).replaceWith('<a class="button button-primary ioa-proceed" data-action="wpio_optimize" data-file="'+file+'">Optimize</a>');
                    }
                    initButtons();
                }else {
                    $(e.currentTarget).parents('tr').find('.optimizationStatus').html("An error occurs");
                }
                
            }              
        });
        });
    };
    initButtons();
    $('#doaction').click(function(){
        $('#wpcontent').prepend('<div id="wpio_wait"><div>Please wait during optimization<br/><span></span></div></div>');
        $('#wpio_wait   ').click(function(){
            window.location.reload();
        });
        if($('#bulk-action-selector-top').val()==='optimize_selected'){
            $('#the-list').find('input[name="image[]"]:checked').parents('tr').find('.ioa-proceed').trigger('click');
        }else if($('#bulk-action-selector-top').val()==='optimize_all'){
            optimizeAll();
        }
    });
    
    optimizeAll = function(){
        $.post(
            ajaxurl, {
                action : 'wpio_optimize_all'
            },
            function(response) {
                response = $.parseJSON(response);
                if(typeof(response.status)!=='undefined' && response.status===true){
                    if(response.datas.continue===true){
                        $('#wpio_wait span').html(response.datas.totalOptimizedImages+' optimized images / '+response.datas.totalImages+' images');
                        optimizeAll();
                    }else{
                        $('#wpio_wait').html("<div>Finished</div>");
                        setTimeout(function() {
                          window.location.href = window.location.href;
                        }, 3000); 
                    }
                } else {                    
                    if (typeof (response.datas) !== 'undefined') {
                        //alert(response.datas.errMsg);
                        $('#wpio_wait').html("<div style='color:#FF3300'>An error occurred: " + response.datas.errMsg+"</div>");     
                        setTimeout(function() {
                          window.location.href = window.location.href;
                        }, 3000);                       
                    }else {
                        $('#wpio_wait span').html("<div>Finished</div>");
                        setTimeout(function() {
                           window.location.href = window.location.href;
                        }, 3000); 
                    }
                  
                }
            });
    };
  
});