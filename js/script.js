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
    startTime = 0;
    $('#doaction').click(function(){      
        if($('#bulk-action-selector-top').val()==='optimize_selected'){
            $('#the-list').find('input[name="image[]"]:checked').parents('tr').find('.ioa-proceed').trigger('click');
        }else if($('#bulk-action-selector-top').val()==='optimize_all'){
            irBox = $("#ir_wait");
            if(irBox.length===0){
                $('body').append('<div id="ir_wait"><div class="ir_innerContent"><section class="progress_wraper">'+ $("#progress_init").html() +'</section></div></div>');
                irBox = $("#ir_wait");
            }
            cWidth  = Math.floor($(window).width()*0.9);
            cHeight = Math.floor($(window).height()*0.9);
            $("#ir_wait .progress_wraper").css('width',cWidth ).css('height',cHeight);
            innerContent = irBox.children(".ir_innerContent");
            innerContent.css('margin-top',(-cHeight/2)+'px').css('margin-left',(-cWidth/2)+'px');                              
            innerContent.css('height','').css('width','').css('top','').css('left','');
                                            
            $('progress').each(function() {
                var max = $(this).val();
                    $(this).val(0).animate({ value: max }, { duration: 2000});
            });

            $('#ir_wait').click(function() {
                window.location.reload();
            });
            startTime = Date.now();
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
                        $('#ir_wait span').html('Processing ...' + response.datas.totalOptimizedImages + ' / ' + response.datas.totalImages + ' images');
                        if(typeof(response.datas.processedImages) != 'undefined') {
                            curTime =  Date.now();
                            remainFiles = response.datas.totalImages - response.datas.totalOptimizedImages;
                            remainTime = (curTime - startTime)/response.datas.processedImages * remainFiles;
                            remainTimeStr = toHHMMSS(Math.floor(remainTime / 1000));
                           //remainMins = Math.floor(remainTime / 60000) ;
                          
                            $('#ir_wait .progress_wraper .timeRemain').html(remainTimeStr +' before finished');
                        }
                        var percent = (response.datas.totalOptimizedImages/response.datas.totalImages)*100;
                        var oldVal = $('#ir_wait progress').val();
                        $('#ir_wait progress').val(percent);
                        $('#ir_wait progress').val(oldVal).animate({ value: percent }, { duration: 500});                                                
                        optimizeAll();
                    }else{
                        $('#ir_wait span').html("Finished");
                        setTimeout(function() {
                          window.location.href = window.location.href;
                        }, 3000); 
                    }
                } else {                    
                    if (typeof (response.datas) !== 'undefined') {
                        //alert(response.datas.errMsg);
                        $('#ir_wait span').html("An error occurred: " + response.datas.errMsg).css('color','#FF3300');     
                        setTimeout(function() {
                           window.location.href = window.location.href;
                        }, 3000);    
                                             
                    }else {
                        $('#ir_wait span').html("Finished");
                        setTimeout(function() {
                           window.location.href = window.location.href;
                        }, 3000); 
                    }
                  
                }
            });
    };
    
    toHHMMSS = function(sec_num) {     
        var hours   = Math.floor(sec_num / 3600);
        var minutes = Math.floor((sec_num - (hours * 3600)) / 60);
        var seconds = sec_num - (hours * 3600) - (minutes * 60);        
        
        if (minutes < 10) {minutes = "0"+minutes;}
        if (seconds < 10) {seconds = "0"+seconds;}
        var time  = '';
        if(hours==0) {
             time    = minutes+'m '+seconds;
        }else {
            if (hours   < 10) {hours   = "0"+hours;}
            time    = hours+'h '+minutes+'m '+seconds+'s';
        }
        
        return time;
    }
  
});