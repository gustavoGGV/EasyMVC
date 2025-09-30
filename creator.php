<?php
ini_set('display_errors', 1);
ini_set('display_startup_erros', 1);
error_reporting(E_ALL);
class Creator
{
    private $con;
    private $servidor;
    private $banco;
    private $usuario;
    private $senha;
    private $tabelas;
    function __construct()
    {
        if (isset($_GET['id']))
            $this->buscaBancodeDados();
        else {
            $this->criaDiretorios();
            $this->conectar(1);
            $this->buscaTabelas();
            $this->ClassesModel();
            $this->ClasseConexao();
            $this->ClassesControl();
            $this->classesView();
            $this->criarIndex();
            $this->ClassesDao();
            $this->compactar();
            header("Location:index.php?msg=2");
        }
    } //fimConsytruct
    function criaDiretorios()
    {
        $dirs = [
            "sistema",
            "sistema/model",
            "sistema/control",
            "sistema/view",
            "sistema/dao",
            "sistema/css"
        ];

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    header("Location:index.php?msg=0");
                }
            }
        }
        copy('estilos.css', 'sistema/css/estilos.css');
    } //fimDiretorios
    function conectar($id)
    {
        $this->servidor = $_REQUEST["servidor"];
        $this->usuario = $_REQUEST["usuario"];
        $this->senha = $_REQUEST["senha"];
        if ($id == 1) {
            $this->banco = $_POST["banco"];
        } else {
            $this->banco = "mysql";
        }
        try {
            $this->con = new PDO(
                "mysql:host=" . $this->servidor . ";dbname=" . $this->banco,
                $this->usuario,
                $this->senha
            );
        } catch (Exception $e) {

            header("Location:index.php?msg=1");
        }
    } //fimConectar
    function buscaBancodeDados()
    {
        try {
            $this->conectar(0);
            $sql = "SHOW databases";
            $query = $this->con->query($sql);
            $databases = $query->fetchAll(PDO::FETCH_ASSOC);
            foreach ($databases as $database) {
                echo "<option>" . $database["Database"] . "</option>";
            }
            $this->con = null;
        } catch (Exception $e) {
            header("Location:index.php?msg=3");
        }
    } //BuscaBD
    function buscaTabelas()
    {
        try {
            $sql = "SHOW TABLES";
            $query = $this->con->query($sql);
            $this->tabelas = $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            header("Location:index.php?msg=3");
        }
    } //fimBuscaTabelas
    function buscaAtributos($nomeTabela)
    {
        $sql = "show columns from " . $nomeTabela;
        $atributos = $this->con->query($sql)->fetchAll(PDO::FETCH_OBJ);
        return $atributos;
    } //fimBuscaAtributos
    function ClassesModel()
    {
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array) $tabela)[0];
            $atributos = $this->buscaAtributos($nomeTabela);
            $nomeAtributos = "";
            $geters_seters = "";
            foreach ($atributos as $atributo) {
                $atributo = $atributo->Field;
                $nomeAtributos .= "\tprivate \${$atributo};\n";
                $metodo = ucfirst($atributo);
                $geters_seters .= "\tfunction get" . $metodo . "(){\n";
                $geters_seters .= "\t\treturn \$this->{$atributo};\n\t}\n";
                $geters_seters .= "\tfunction set" . $metodo . "(\${$atributo}){\n";
                $geters_seters .= "\t\t\$this->{$atributo}=\${$atributo};\n\t}\n";
            }
            $nomeClasse = ucfirst($nomeTabela);
            $conteudo = <<<EOT
<?php
class {$nomeClasse} {
{$nomeAtributos}
{$geters_seters}
}
?>
EOT;
            file_put_contents("sistema/model/{$nomeTabela}.php", $conteudo);
        }
    } //fimModel
    function ClasseConexao()
    {
        $conteudo = <<<EOT

<?php
class Conexao {
    private \$server;
    private \$banco;
    private \$usuario;
    private \$senha;
    function __construct() {
        \$this->server = '{$this->servidor}';
        \$this->banco = '{$this->banco}';
        \$this->usuario = '{$this->usuario}';
        \$this->senha = '{$this->senha}';
    }
    
    function conectar() {
        try {
            \$conn = new PDO(
                "mysql:host=" . \$this->server . ";dbname=" . \$this->banco,\$this->usuario,
                \$this->senha
            );
            return \$conn;
        } catch (Exception \$e) {
            echo "Erro ao conectar com o Banco de dados: " . \$e->getMessage();
        }
    }
}
?>
EOT;
        file_put_contents("sistema/model/conexao.php", $conteudo);
    } //fimConexao
    function ClassesControl()
    {
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array)$tabela)[0];
            $atributos = $this->buscaAtributos($nomeTabela);
            $nomeClasse = ucfirst($nomeTabela);
            $posts = "";
            foreach ($atributos as $atributo) {
                $atributo = $atributo->Field;
                $posts .= "\$this->{$nomeTabela}->set" . ucFirst($atributo) .
                    "(\$_POST['{$atributo}']);\n\t\t";
            }

            $conteudo = <<<EOT
<?php
require_once("../model/{$nomeTabela}.php");
require_once("../dao/{$nomeTabela}Dao.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class {$nomeClasse}Control {
    private \${$nomeTabela};
    private \$acao;
    private \$dao;
    public function __construct(){
       \$this->{$nomeTabela}=new {$nomeClasse}();
      \$this->dao=new {$nomeClasse}Dao();
      \$this->acao=\$_GET["a"];
      \$this->verificaAcao(); 
    }
    function verificaAcao(){
       switch(\$this->acao){
          case 1:
            \$erro = \$this->inserir();

            print_r(\$erro);
          case 2:
            \$this->excluir();
          case 3:
            \$erro = \$this->alterar();
            
            print_r(\$erro);
          break;
       }
    }
  
    function inserir(){
        {$posts}
        \$erro = \$this->dao->inserir(\$this->{$nomeTabela});

        return \$erro;
    }
    function excluir(){
        \$this->dao->excluir(\$_REQUEST['id']);
    }
    function alterar(){
        {$posts}
        \$erro = \$this->dao->alterar(\$this->{$nomeTabela});

        return \$erro;
    }
    function buscarId({$nomeClasse} \${$nomeTabela}){}
    function buscaTodos(){}
}
new {$nomeClasse}Control();
?>
EOT;
            file_put_contents("sistema/control/{$nomeTabela}Control.php", $conteudo);
        }
    } //fimControl
    function compactar()
    {
        $folderToZip = 'sistema';
        $outputZip = 'sistema.zip';
        $zip = new ZipArchive();
        if ($zip->open($outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return false;
        }
        $folderPath = realpath($folderToZip);  // Corrigido aqui
        if (!is_dir($folderPath)) {
            return false;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folderPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($folderPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }

        return $zip->close();
    } //fimCompactar
    function ClassesDao()
    {
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array)$tabela)[0];
            $nomeClasse = ucfirst($nomeTabela);
            $atributos = $this->buscaAtributos($nomeTabela);
            $id = "";
            foreach ($atributos as $atributo) {
                if ($atributo->Key == "PRI")
                    $id = $atributo->Field;
            }

            $atributosMapeados = array_map(function ($obj) {
                return $obj->Key != "PRI" ? $obj->Field : null;
            }, $atributos);

            $atributosMapeadosComId = array_map(function ($obj) {
                return $obj->Field;
            }, $atributos);

            $sqlCols = implode(', ', $atributosMapeadosComId);
            $placeholders = implode(', ', array_fill(0, count($atributosMapeadosComId), '?'));
            $vetAtributosComId = [];
            $vetAtributos = [];
            $AtributosMetodosComId = "";

            $updates = "";
            $i = 1;
            foreach ($atributosMapeados as $atributo) {
                if ($atributo) {
                    $i++;

                    if ($i === count($atributosMapeados)) {
                        $updates .= $atributo . " = ? ";

                        continue;
                    }
                    $updates .= $atributo . " = ?, ";
                }
            }

            foreach ($atributosMapeadosComId as $atributo) {
                //$id=$atributos[0];
                $atr = ucfirst($atributo);
                array_push($vetAtributosComId, "\${$atributo}");
                $AtributosMetodosComId .= "\${$atributo}=\$obj->get{$atr}();\n";
            }

            foreach ($atributosMapeados as $atributo) {
                //$id=$atributos[0];
                if ($atributo) {
                    $atr = ucfirst($atributo);
                    array_push($vetAtributos, "\${$atributo}");
                }
            }
            $atributosOkComId = implode(",", $vetAtributosComId);
            $atributosOk = implode(",", $vetAtributos);
            $conteudo = <<<EOT
<?php
require_once("../model/conexao.php");
class {$nomeClasse}Dao {
    private \$con;
    public function __construct(){
       \$this->con=(new Conexao())->conectar();
    }
function inserir(\$obj) {
try {
    \$sql = "INSERT INTO {$nomeTabela} ({$sqlCols}) VALUES ({$placeholders})";
    \$stmt = \$this->con->prepare(\$sql);
    {$AtributosMetodosComId}
    \$stmt->execute([{$atributosOkComId}]);
    header("Location:../view/{$nomeClasse}.php");
} catch (PDOException \$e) {
    return \$e;
}   
}
function listaGeral(){
    \$sql = "select * from {$nomeTabela}";
    \$query = \$this->con->query(\$sql);
    \$dados = \$query->fetchAll(PDO::FETCH_ASSOC);
    return \$dados;
}
 function buscaPorId(\$id){
    \$sql = "select * from {$nomeTabela} where {$id}=\$id";
    \$query = \$this->con->query(\$sql);
    \$dados = \$query->fetch(PDO::FETCH_ASSOC);
    return \$dados;
}   
function excluir(\$id){
    \$sql = "delete from {$nomeTabela} where {$id}=\$id";
    \$query = \$this->con->query(\$sql);
    header("Location:../view/lista{$nomeClasse}.php");
}
function alterar(\$obj) {
try{
    \$sql = "UPDATE {$nomeTabela} SET {$updates} where {$id} = ?";
    \$stmt = \$this->con->prepare(\$sql);
    {$AtributosMetodosComId}
    \$stmt->execute([{$atributosOk}, intval(\${$id}) ? (int) \${$id} : \${$id}]);
    header("Location:../view/lista{$nomeClasse}.php");
} catch (PDOException \$e) {
    return \$e;
}
}
}
?>
EOT;
            file_put_contents("sistema/dao/{$nomeTabela}Dao.php", $conteudo);
        }
    } //fimDao
    function classesView()
    {
        //formulários
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array) $tabela)[0];
            $atributos = $this->buscaAtributos($nomeTabela);
            $formCampos = "";
            $formCamposComId = "";
            foreach ($atributos as $atributo) {
                $formCamposComId .= "<label for='{$atributo->Field}'>{$atributo->Field}</label>\n";

                if ($atributo->Type === "int") {
                    $formCamposComId .= "<input type='number' " .
                        "value='<?php echo \$obj?\$obj['{$atributo->Field}']:''; ?>'" .
                        "name='{$atributo->Field}' class='mt-3'><br>\n";

                    if ($atributo->Key === "PRI") {
                        $formCampos .= "<input type='hidden' " .
                            "value='<?php echo \$obj?\$obj['{$atributo->Field}']:''; ?>'" .
                            "name='{$atributo->Field}' class='mt-3'><br>\n";

                        continue;
                    }

                    $formCampos .= "<label for='{$atributo->Field}'>{$atributo->Field}</label>\n";
                    $formCampos .= "<input type='number' " .
                        "value='<?php echo \$obj?\$obj['{$atributo->Field}']:''; ?>'" .
                        "name='{$atributo->Field}' class='mt-3'><br>\n";

                    continue;
                }
                $formCamposComId .= "<input type='text' " .
                    "value='<?php echo \$obj?\$obj['{$atributo->Field}']:''; ?>'" .
                    "name='{$atributo->Field}' class='mt-3'><br>\n";

                if ($atributo->Key === "PRI") {
                    $formCampos .= "<input type='hidden' " .
                        "value='<?php echo \$obj?\$obj['{$atributo->Field}']:''; ?>'" .
                        "name='{$atributo->Field}' class='mt-3'><br>\n";

                    continue;
                }

                $formCampos .= "<label for='{$atributo->Field}'>{$atributo->Field}</label>\n";
                $formCampos .= "<input type='text' " .
                    "value='<?php echo \$obj?\$obj['{$atributo->Field}']:''; ?>'" .
                    "name='{$atributo->Field}' class='mt-3'><br>\n";
            }

            $conteudo = <<<HTML
<?php
    require_once('../dao/{$nomeTabela}Dao.php');
    \$obj=null;
    if(isset(\$_GET['id']))
    \$obj=(new {$nomeTabela}Dao())->buscaPorId(\$_GET['id']);
    \$acao=\$obj?3:1;
?>
<html>
    <head>
        <title><?= isset(\$_GET['id']) ? 'Alterar' : 'Cadastrar' ?> {$nomeTabela}</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    </head>
    <body>
        <div class="container p-5 d-flex flex-column justify-content-center align-items-center">
            <form action="./../control/{$nomeTabela}Control.php?a=<?= \$acao ?>" method="post" class="card col-4 d-flex flex-column justify-content-center p-5">
            <h1 class="mb-4"><?= isset(\$_GET['id']) ? 'Alterar' : 'Cadastrar' ?> {$nomeTabela}</h1>
                <?php if(isset(\$_GET['id'])): ?>
                    {$formCampos}
                <?php else: ?>
                    {$formCamposComId}
                <?php endif; ?>
                <button type="submit" class="mt-3 btn btn-primary">Enviar</button>
            </form>
        </div>  
    </body>
</html>
HTML;
            file_put_contents("sistema/view/{$nomeTabela}.php", $conteudo); // Exemplo salvando como arquivo
        }
        //Listas
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array)$tabela)[0];
            $nomeTabelaUC = ucfirst($nomeTabela);
            $atributos = $this->buscaAtributos($nomeTabela);
            $attr = "";
            $id = "";
            foreach ($atributos as $atributo) {
                if ($atributo->Key == "PRI")
                    $id = "{\$dado['{$atributo->Field}']}";

                $attr .= "echo \"<td>{\$dado['{$atributo->Field}']}</td>\";\n";
            }
            $conteudo = "";
            $conteudo = <<<HTML

<html>
    <head>
        <title>Lista de {$nomeTabela}</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    </head>
    <body>
        <div class="vh-100 container d-flex flex-column justify-content-center">
            <h1>Lista de {$nomeTabelaUC}</h1>

      <?php
      require_once("../dao/{$nomeTabela}Dao.php");
   \$dao=new {$nomeTabela}DAO();
   \$dados=\$dao->listaGeral();
   \$colunas=array_keys(\$dados[0]);

    echo "<table class='table table-striped'>";
        echo "<tr>\n";
        foreach(\$colunas as \$coluna){
            echo "<th>" . \$coluna . "</th>\n";
        }
        echo "<th>Excluir</th>\n";
        echo "<th>Alterar</th>\n";
        echo "</tr>\n";
    foreach(\$dados as \$dado){
        echo "<tr>";
       {$attr}
       echo "<td>".
       "<a href='./../control/{$nomeTabela}Control.php?id={$id}&a=2' class='text-decoration-none text-danger fs-2'><i class='bi bi-trash3-fill'></i></a>".
       "</td>";
       echo "<td>" . 
        "<a href='./{$nomeTabela}.php?id={$id}' class='text-decoration-none text-warning fs-2'><i class='bi bi-pencil-fill'></i></a>" .
       "</td>";
       echo "</tr>";
    }
    echo "</table>";
     ?>
        </div>

    </body>
</html>
HTML;
            file_put_contents("sistema/view/lista{$nomeTabelaUC}.php", $conteudo);
        }
    }

    function criarIndex()
    {
        //página inicial

        $conteudo = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Sistema {$this->banco}</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
        }

        /* Cabeçalho */
        .cabecalho {
            width: 100%;
            height: 200px;
            background-color: #2c3e50;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            font-weight: bold;
        }

        /* Menu principal */
        .menu {
            width: 100%;
            height: 100px;
            background-color: #34495e;
            display: flex;
            align-items: center;
            padding-left: 20px;
        }

        .menu ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            gap: 40px;
        }

        .menu li {
            position: relative;
        }

        .menu a {
            color: white;
            text-decoration: none;
            font-size: 18px;
            padding: 10px;
            display: block;
        }

        /* Submenu */
        .menu li ul {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background-color: #2c3e50;
            list-style: none;
            padding: 0;
            margin: 0;
            min-width: 200px;
        }

        .menu li ul li a {
            padding: 10px;
            font-size: 16px;
        }

        /* Exibir submenu ao passar o mouse */
        .menu li:hover ul {
            display: block;
        }

        /* Conteúdo */
        .conteudo {
            min-height: calc(100vh - 300px); /* altura total - cabeçalho (200) - menu (100) */
            padding: 20px;
            background-color: #ecf0f1;
        }
    </style>
</head>
<body>
<div class="cabecalho">
    {$this->banco}
</div>

<div class="menu">
    <ul>
        <li>
            <a>Cadastros</a>
            <ul>
HTML;
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array)$tabela)[0];
            $nomeTabelaUC = ucfirst($nomeTabela);
            $conteudo .= "<a href='./view/" . $nomeTabela . ".php' onclick='sumirComTexto()' target='conteudo-sistema'>Cadastro de " . $nomeTabelaUC . "</a>";
        }
        $conteudo .= <<<HTML
            </ul>
        </li>
        <li>
            <a>Relatórios</a>
            <ul>
HTML;
        foreach ($this->tabelas as $tabela) {
            $nomeTabela = array_values((array)$tabela)[0];
            $nomeTabelaUC = ucfirst($nomeTabela);
            $conteudo .= "<a href='./view/lista" . $nomeTabela . ".php' onclick='sumirComTexto()' target='conteudo-sistema'>Relatório de " . $nomeTabelaUC . "</a>";
        }
        $conteudo .= <<<HTML
            </ul>
        </li>
    </ul>
</div>

<div class="conteudo">
    <div id="texto">
        <h2>Bem-vindo!</h2>
        <p>Esta é a área de conteúdo do sistema.</p>
    </div>
    <iframe frameborder="0" name="conteudo-sistema" height="750" width="100%"></iframe>
</div>
</body>

<script>
const sumirComTexto = () => {
    document.getElementById("texto").style.display = "none";
}
</script>

</html>

HTML;
        file_put_contents("sistema/index.html", $conteudo);
    }
} //fimView

new Creator();
