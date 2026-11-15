<?php

/**
 * Preguntas de control para medir la búsqueda y las respuestas: [disciplina, pregunta, reglas esperadas].
 * Las reglas esperadas se han elegido leyendo el texto del reglamento (January 2026).
 * Se ejecutan con: php artisan reglamento:evaluar [--respuesta]
 */
return [
    ['S', 'Cual es la penalización por usar slug en un ejercicio de birdshot', ['10.4.9']],
    ['S', '¿Qué pasa si disparo con posta (buckshot) en un recorrido que exige perdigones?', ['10.4.10']],
    ['H', 'Se me cae la pistola durante el recorrido, ¿qué sanción tengo?', ['10.5.3']],
    ['H', '¿Quién puede recoger un arma que se ha caído al suelo?', ['10.5.15']],
    ['R', '¿Qué penalización hay por pisar la línea de falta mientras disparo?', ['10.2.1']],
    ['P', 'Mi arma se encasquilla y no la arreglo en dos minutos, ¿qué ocurre?', ['5.7.3']],
    ['M', 'Un blanco metálico no estaba repuesto cuando empecé, ¿puedo repetir el recorrido?', ['4.7.2']],
    ['H', '¿Cuánto tiempo puede pasar entre la voz de Standby y la señal de inicio?', ['8.3.3']],
    ['S', '¿Qué ocurre si uso una munición distinta a la indicada para el ejercicio y caen blancos metálicos?', ['9.5.11', '10.2.12']],
    ['T', '¿Cuándo me descalifican por el dedo dentro del guardamonte al solucionar un encasquillamiento?', ['10.5.9']],
    ['H', '¿Cómo hay que transportar una pistola fuera de la zona de seguridad?', ['5.2.1']],
    ['S', '¿A qué distancia mínima de los blancos deben estar las líneas de falta con perdigón?', ['2.1.3.1']],
    ['H', '¿Qué pasa si el factor de potencia no alcanza el mínimo de Major?', ['5.6.3.8']],
    ['R', 'Un competidor tiene una conducta antideportiva, ¿qué consecuencia tiene?', ['10.6.1']],
    ['H', 'Si no estoy de acuerdo con la decisión del árbitro, ¿cómo apelo?', ['11.1.3']],
    ['S', '¿Puede el árbitro pedirme que le enseñe mi arma en cualquier momento?', ['5.1.6']],
    ['S', '¿Es descalificación un disparo que cae al suelo a menos de 3 metros del competidor?', ['10.4.2']],
    ['M', 'Dejé el arma cargada apuntando hacia atrás en la zona de tiro, ¿qué me pasa?', ['10.5.2']],
];
