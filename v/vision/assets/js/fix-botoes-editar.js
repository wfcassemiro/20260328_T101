/**
 * Fix para Botões de Editar Palestras - Translators101
 * Este arquivo corrige automaticamente os botões que não respondem ao clique
 */

(function() {
    'use strict';
    
    // Aguardar o DOM estar carregado
    function inicializar() {
        console.log('🔧 Translators101: Iniciando correção dos botões de editar...');
        
        // Aplicar fix imediatamente
        corrigirBotoesEditar();
        
        // Observar mudanças no DOM para novos botões
        if (typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver(function(mutations) {
                let precisaCorrigir = false;
                
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList' || mutation.type === 'attributes') {
                        const novosBotoes = document.querySelectorAll('[onclick*="editLecture"]:not([data-t101-fixed])');
                        if (novosBotoes.length > 0) {
                            precisaCorrigir = true;
                        }
                    }
                });
                
                if (precisaCorrigir) {
                    setTimeout(corrigirBotoesEditar, 100);
                }
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['onclick']
            });
        }
    }
    
    // Função principal de correção
    function corrigirBotoesEditar() {
        const botoesEditar = document.querySelectorAll('[onclick*="editLecture"]:not([data-t101-fixed])');
        
        if (botoesEditar.length === 0) return;
        
        console.log(`🔄 Corrigindo ${botoesEditar.length} botões de editar...`);
        
        botoesEditar.forEach(function(botao, index) {
            const onclickOriginal = botao.getAttribute('onclick');
            const match = onclickOriginal ? onclickOriginal.match(/editLecture\(['"]([^'"]+)['"]\)/) : null;
            
            if (match) {
                const lectureId = match[1];
                
                // Remover onclick que não funciona
                botao.removeAttribute('onclick');
                
                // Adicionar novo evento que funciona
                botao.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('🚀 Translators101: Editando palestra ID:', lectureId);
                    editarPalestraCorrigido(lectureId);
                    return false;
                };
                
                // Marcar como corrigido
                botao.setAttribute('data-t101-fixed', 'true');
                
                console.log('✅ Botão ' + (index + 1) + ' corrigido (ID: ' + lectureId + ')');
            }
        });
    }
    
    // Função de edição corrigida
    function editarPalestraCorrigido(lectureId) {
        console.log('🎯 Editando palestra:', lectureId);
        
        // Definir título do modal
        var modalTitle = document.getElementById('modalTitle');
        if (modalTitle) {
            modalTitle.textContent = 'Editar Palestra';
        }
        
        // Definir ID no formulário
        var lectureIdInput = document.getElementById('lectureId');
        if (lectureIdInput) {
            lectureIdInput.value = lectureId;
        }
        
        // Se for palestra padrão
        if (lectureId.indexOf('default-') === 0) {
            console.log('📋 Carregando palestra padrão...');
            
            if (typeof getDefaultLectureData === 'function') {
                var lectureData = getDefaultLectureData(lectureId);
                if (typeof populateLectureForm === 'function') {
                    populateLectureForm(lectureData);
                } else {
                    popularFormulario(lectureData);
                }
            }
            
            mostrarModal();
            return;
        }
        
        // Buscar dados da API
        console.log('🌐 Buscando dados da API...');
        
        fetch('manage_announcements.php?id=' + encodeURIComponent(lectureId))
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.json();
            })
            .then(function(data) {
                console.log('📊 Dados recebidos:', data);
                
                // Popular o formulário
                if (typeof populateLectureForm === 'function') {
                    populateLectureForm(data);
                } else {
                    popularFormulario(data);
                }
                
                mostrarModal();
            })
            .catch(function(error) {
                console.error('❌ Erro ao carregar dados:', error);
                alert('Erro ao carregar dados da palestra: ' + error.message);
            });
    }
    
    // Função para popular formulário manualmente
    function popularFormulario(data) {
        var campos = {
            'lectureTitle': data.title,
            'lectureSpeaker': data.speaker,
            'lectureDate': data.lecture_date,
            'lectureTime': data.lecture_time,
            'lectureSummary': data.description
        };
        
        for (var id in campos) {
            if (campos.hasOwnProperty(id)) {
                var elemento = document.getElementById(id);
                if (elemento && campos[id]) {
                    elemento.value = campos[id];
                }
            }
        }
        
        console.log('📝 Formulário populado');
    }
    
    // Função para mostrar modal
    function mostrarModal() {
        var modal = document.getElementById('lectureModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.style.visibility = 'visible';
            modal.style.opacity = '1';
            modal.style.zIndex = '9999';
            
            console.log('✅ Modal exibido');
            
            // Focar no primeiro campo
            setTimeout(function() {
                var firstInput = modal.querySelector('input[type="text"], textarea');
                if (firstInput) {
                    firstInput.focus();
                }
            }, 100);
        } else {
            console.error('❌ Modal não encontrado');
        }
    }
    
    // Disponibilizar funções globalmente
    window.editarPalestraCorrigido = editarPalestraCorrigido;
    window.corrigirBotoesEditar = corrigirBotoesEditar;
    
    // Inicializar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializar);
    } else {
        inicializar();
    }
    
})();