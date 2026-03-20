'use stric'

let userQuant = document.querySelector('.usersQuant');
let usersList = document.querySelector('.usuariosLista');
let titleDate = document.querySelector('.titleDate');

function usuarios( usersArray ){
    userQuant.innerText = usersArray.total;
    titleDate.innerText = `${new Date().getDate()}/${new Date().getMonth() + 1}/${new Date().getFullYear()}`
    let meses = {
        "enero": 0, "febrero": 1, "marzo": 2, "abril": 3, "mayo": 4, "junio": 5,
        "julio": 6, "agosto": 7, "septiembre": 8, "octubre": 9, "noviembre": 10, "diciembre": 11
    }


    usersArray.usuarios.forEach( user => {
        var day, month, year;
        var fechaNac = user.nacimiento.split(' ');
        var edad;

        day = fechaNac[0];
        month = meses[fechaNac[2].toLowerCase()];
        year = fechaNac[4];

        var fechaObj = new Date(year, month, day);

        edad = new Date().getFullYear() - fechaObj.getFullYear();

        if( new Date().getMonth() < fechaObj.getMonth() ){
            edad--;
        } else if( new Date().getMonth() == fechaObj.getMonth() ){
            if( new Date().getDate() < fechaObj.getDate() ){
                edad--;
            }
        }

        usersList.insertAdjacentHTML('beforeend', `
            <div class="w-full md:w-6/12 lg:w-4/12 p-1">
                <div class="h-full border rounded-sm p-2 text-xs sm:text-sm ${edad < 15 ? 'border-red-700' : 'border-white' }">
                    <h3 class="capitalize font-bold">${user.nombre} ${user.apellido}</h3>
                    <ul class="mb-2">
                        <li>${user.nacimiento}</li>
                        <li><span class="font-bold">Edad: </span>${edad}</li>
                        <li class="uppercase">${user.curp}</li>
                        <li class="lowercase">${user.email}</li>
                        <li class="capitalize">${user.estado}</li>
                        <li class="capitalize">${user.municipio}</li>
                        <li class="capitalize">${user.localidad}</li>
                    </ul>
                </div>
            </div>
        ` );

        /*
            nombre: "nombre"
            apellido: "apellido"
            curp: "CURP"
            email: "email"
            estado: "Baja California Sur"
            localidad: "ciudad"
            municipio: "colonia"
            nacimiento: "fecha nacimiento"
        */
    });
}

async function loadUsers(){
    let path = 'scrapperMoodleUsersNews.php';
    let dateInput = document.querySelector('#id_date');

    let day, month, year;

    if(dateInput && dateInput.value){
        [year, month, day] = dateInput.value.split('-');
        path += `?day=${Number(day)}&month=${Number(month)}&year=${year}`;

        // Evitar cache del navegador
        path += path.includes('?') ? '&' : '?';
        path += `_=${Date.now()}`;

        // limpiar lista antes de agregar
        usersList.innerHTML = '';
        userQuant.innerText = '';
        titleDate.innerText = '';
    }

    // mostrar fecha en título (opcional)
    titleDate.innerText = `${day}/${month}/${year}`;

    await fetch(path)
        .then( res => res.json() )
        .then( users => {
            console.log(users.total);
            usuarios(users)
        } )
}

loadUsers()