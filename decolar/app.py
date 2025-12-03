from flask import Flask, json, render_template, request, redirect, jsonify
import sqlite3
import requests
from datetime import datetime

app = Flask(__name__, template_folder='.')

# --- Base URLs das Operadoras Aéreas ---
# As URLs devem apontar para os respectivos servidores de cada operadora
LATAM_URL = 'http://latam:80' # Exemplo
GOL_URL = 'http://gol:80'    # Exemplo
OPERADORAS = {
    'LATAM': LATAM_URL,
    'Gol': GOL_URL
}
# ----------------------------------------


@app.route('/listar', methods=['GET', 'POST'])
def listar ():
    voos_encontrados = []
    origem = request.values.get('origem')
    destino = request.values.get('destino')
    data = request.values.get('data')
    
    # 1. Lógica de busca agregada
    if request.method == 'POST' and origem and destino and data:
        for operadora, base_url in OPERADORAS.items():
            try:
                # Requisição para buscar voos na operadora
                response = requests.get(
                    f'{base_url}/api_voos.php', 
                    params={'origem': origem, 'destino': destino, 'data': data}
                )
                response.raise_for_status() # Lança exceção para erros HTTP 4xx/5xx
                
                voos_operadora = response.json()
                
                # 2. Adicionar a informação da operadora e formatar
                for voo in voos_operadora:
                    voo['operadora'] = operadora
                    # Cria um ID único para a compra no Decolar
                    voo['decolar_id'] = f"{operadora}_{voo['id']}"
                    
                    try:
                        dt_obj = datetime.strptime(voo['datahora'], '%Y-%m-%d %H:%M:%S')
                        voo['datahora_formatada'] = dt_obj.strftime('%d/%m/%Y %H:%M')
                    except ValueError:
                         voo['datahora_formatada'] = voo['datahora']

                voos_encontrados.extend(voos_operadora)
                
            except requests.exceptions.RequestException as e:
                print(f"Erro ao buscar voos na {operadora}: {e}")
                
    
    return render_template(
        'listar.html', 
        voos=voos_encontrados, 
        origem=origem, 
        destino=destino, 
        data=data
    )

@app.route('/comprar', methods=['GET'])
def comprar ():
    # Recebe os detalhes do voo selecionado via query parameters
    decolar_id = request.args.get('decolar_id')
    origem = request.args.get('origem')
    destino = request.args.get('destino')
    datahora = request.args.get('datahora')
    operadora = request.args.get('operadora')
    aviao = request.args.get('aviao')
    preco = request.args.get('preco')
    
    if not decolar_id:
        return redirect('/listar')

    return render_template(
        'comprar.html', 
        decolar_id=decolar_id,
        origem=origem,
        destino=destino,
        datahora=datahora,
        operadora=operadora,
        aviao=aviao,
        preco=preco
    )

@app.route('/confirmar', methods=['POST'])
def confirmar ():
    decolar_id = request.form.get('decolar_id')
    cpf = request.form.get('cpf')
    nome = request.form.get('nome')
    
    if not decolar_id or not cpf or not nome:
        return render_template('confirmar.html', resultado=False)

    # 1. Separar a operadora e o ID do voo original
    try:
        operadora, voo_id = decolar_id.split('_', 1)
    except ValueError:
        return render_template('confirmar.html', resultado=False)

    base_url = OPERADORAS.get(operadora)
    
    if not base_url:
        return render_template('confirmar.html', resultado=False)

    resultado_compra = False
    try:
        # 2. Requisição POST para o API de compra da operadora
        response = requests.post(
            f'{base_url}/api_comprar.php', 
            data={'voo_id': voo_id, 'cpf': cpf, 'nome': nome}
        )
        response.raise_for_status()
        
        # 3. Verificar o resultado do JSON (success: true/false)
        json_response = response.json()
        resultado_compra = json_response.get('success', False)
        
    except requests.exceptions.RequestException as e:
        print(f"Erro ao tentar comprar na {operadora}: {e}")
        resultado_compra = False

    return render_template('confirmar.html', resultado=resultado_compra)

@app.route('/', methods=['GET', 'POST'])
def index():
	return redirect('/listar')
	
app.run(port=5001, use_reloader=True)