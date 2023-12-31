<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ConsumerData;

class ConvertCSVController extends Controller
{

   public function index() {
      $isEmpty = Product::all()->count() == 0;

      return view('upload_product_data', [
         'isEmpty' => $isEmpty,
      ]);
   }

   // this function opens the designated CSV file and uploads each column into the Product SQL database
   public function uploadCSV() {

      $data = request()->validate(['file' => 'required']);


      $CSVfile = fopen(request()->file('file'), 'r');
      $header = fgetcsv($CSVfile, null, ';');

      $expectedHeaders = ['link','category_1','category_2','product_name','price','price_per','ingredients','allergen_information','brand','recycling_information','brand_details'];
      
      if ($header !== $expectedHeaders) {
         return redirect()->back()->withErrors(['file' => 'Invalid CSV file uploaded, Please ensure your CSV header\'s are in the following order: <i>' . implode("; ", $expectedHeaders) . '</i>.']);
      }
      $lineOfCsv = 1; 

      $numInserted = 0; // store number of records inserted into the db
      $totalProcessed = 0; // store total number of processed records (either already in db or inserted)
      while(!feof($CSVfile)) {
         $lineOfCsv++;
         $line = fgetcsv($CSVfile, null, ';');

         // ignore line if blank
         if(($line[0] ?? null) === null) {
            continue;
         }
         
         $productCat = ProductCategory::where('product_category_name', $line[1])->first();
         
         if ($productCat === null) {
            $new = ProductCategory::create(['product_category_name' => $line[1]]);
            $productCatId = $new['id'];
         } else {
            $productCatId = $productCat['id'];
         }

         $product = [];
         $product['category'] = $line[1] ?? '';
         
         $product['category_id'] = $productCatId;
         $product['product_link'] = $line[0] ?? '';
         $product['subcategory'] = $line[2] ?? '';
         $product['product_name'] = $line[3] ?? '';
         $product['price_£'] = $line[4] ?? '';
         $product['price_per'] = $line[5] ?? '';
         $product['ingredients'] = $line[6] ?? '';
         $product['allergen_information'] = $line[7] ?? '';
         $product['brand'] = $line[8] ?? '';
         $product['recycling_information'] = $line[9] ?? '';
         $product['brand_details'] = $line[10] ?? '';

         // add product to database if it doesn't already exist
         $create = Product::firstOrCreate($product);
         if ($create->wasRecentlyCreated) {
            $numInserted++;
         }
         $totalProcessed++;
      }

      fclose($CSVfile);

      // display import successful message
      if ($totalProcessed !== $numInserted) {
         request()->session()->flash('success', $numInserted . ' products were successfully imported. ' . $totalProcessed-$numInserted . ' products were not imported (already present in the database).');
      } else {
         request()->session()->flash('success', $totalProcessed . ' products were successfully imported.');
      }

      return redirect(route('upload_product_data'));
   } 

   // generate user data
   function generateUserData() {
      // define options
      $genders = array('Male', 'Female');
      $cities = array("London", "Manchester", "Birmingham", "Liverpool", "Glasgow", "Edinburgh", "Bristol", "Leeds", "Newcastle", "Sheffield", "Cardiff", "Belfast", "Leicester", "Leicester");
      $incomes = array(40000, 60000, 80000, 100000);
      $numDependants = array(0, 1, 2, 3, 3, 4, 4);
      $dietaryRequirements = array('Vegetarian', 'Vegan', 'Gluten-free', 'Lactose-free', '', '', '', '', '', '', '');
  
      
      // generate data for each field
      $gender = $genders[rand(0, count($genders) - 1)];
      $age = rand(18, 80);
      $city = $cities[rand(0, count($cities) - 1)];
      if ($age < 25) {
         $income = rand(1.6,4) * 10000;
      } else {
         $income = $incomes[rand(0, count($incomes) - 1)];
      } 
      if ($age < 25) {
         $numDependant = rand(0,1);
      } else {
         $numDependant = $numDependants[rand (0, count($numDependants) - 1)];
      }
      $dietaryRequirement = $dietaryRequirements[rand(0, count($dietaryRequirements) - 1)];
      
      return [
         'gender' => $gender,
         'age' => $age,
         'city' => $city,
         'income' => $income,
         'number_of_dependants' => $numDependant,
         'dietary_requirements' => $dietaryRequirement,
      ];
   }
   

   // generate shopping list
   function generateShoppingList($income, $dietaryRequirements) {
      // Predefined shopping list template
      $items = array(
          "Fruits" => array('Apple', 'Banana', 'Orange', 'Kiwi', 'Pineapple', 'Mango', 'Strawberry', 'Blueberry', 'Raspberry', 'Grape', 'Cherry', 'Lemon', 'Lime', 'Grapefruit', 'Pear', 'Peach', 'Plum', 'Apricot', 'Watermelon', 'Pomegranate'),
          "Vegetables" => array('Carrot', 'Broccoli', 'Cabbage', 'Tomato', 'Potato', 'Spinach', 'Kale', 'Lettuce', 'Cauliflower', 'Pepper', 'Cucumber', 'Onion', 'Garlic', 'Ginger', 'Pumpkin', 'Zucchini', 'Eggplant', 'Asparagus', 'Mushroom', 'Brussels sprouts'),
          "Meat and Poultry" => array("Chicken", "Beef", "Pork", "Chicken Nuggets"),
          "Milk" => array("Whole Milk", "Semi-Skimmed Milk", "Oat Milk"),
          "Dairy Products + Eggs" => array("Cheddar Cheese", "Butter", "Yogurt", "Eggs"),
          "Bakery" => array("White Bread", "Brown Bread", "Bread Rolls"),
          "Canned Foods" => array("Kidney Beans", "Beans", "Soup")
      );


      // ----------GENERATE CATEGORIES
      $list = [];
      foreach ($items as $category => $categoryItem) {
         // 1 in 5 chance of skipping a category entirely
         if (rand(1, 5) === 5) {
            continue;
         }

         // choose how many items a user will buy from a category
         $num_items = rand(1, count($categoryItem)); 
         
         // randomly pick items from each category $num_items amount of times
         for ($i = 0; $i < $num_items; $i++) {
            array_push($list, $categoryItem[rand(0, count($categoryItem) - 1)]);
         }
      }
      $list = array_unique($list);

      // ---------------CONVERT INTO REAL ITEMS
      // 1. limit dataset to exclude products that don't match consumer restrictions e.g. vegan products only
      switch($dietaryRequirements) {
         case 'Vegetarian':
            $dataset = Product::whereIn('subcategory',['Vegetarian & Vegan Foods', 'Fruit', 'Yoghurts'])
            ->orWhere('product_name', 'like', '%vegetarian%')
            ->orWhere('product_name', 'like', '%vegan%');
            break;
         case 'Vegan':
            $dataset = Product::whereIn('subcategory',['Vegetarian & Vegan Foods', 'Fruit'])
            ->orWhere('product_name', 'like', '%vegan%')
            ->where('product_name', 'not like', '%vegetarian%')
            ->where('ingredients', 'not like','%lactose%')
            ->where('allergen_information', 'not like', '%egg%')
            ->where('product_name', 'not like', '%egg%');
            break;
         case 'Gluten-free':
            $dataset = Product::where('product_name', 'like', '%gluten%') // most likely gluten-free
            ->orWhere('allergen_information', 'like', '%free from gluten%')
            ->orWhereIn('subcategory', ['Fruit']);
            break;
         case 'Lactose-free':
            $dataset = Product::where('ingredients', 'not like', '%lactose%')
            ->orWhereIn('subcategory', ['Fruit']);
            break;
         default:
            $dataset = new Product;
            break;
      }

      switch($income) {
         case 20000:
            $dataset = $dataset->where('product_name', 'not like', '%M&S%');
             break;

         case 80000:
         case 100000:
            $dataset = $dataset->where('product_name', 'like', '%M&S%');
               break;   

         default:
             break;
     }
     
$dataset = $dataset->get();
         
      

      // 2. use limited dataset to find a matching item for each category in ($list) 
      $real = [];
      foreach($list as $item) {
         $realItems = $dataset->filter(function($row) use($item) {
            return str_contains($row['product_name'], $item);
         });   

         if (!$realItems->isEmpty()) {
            array_push($real, $realItems->random());
         }
      }

      return $real;
   }


   public function create_shopping_list() {
      $quantity = request()["quantity"];
      // generate users
      for ($i = 0; $i < $quantity; $i++) {
            $consumer = ConsumerData::create($this->generateUserData());
            
            for ($i = 0; $i < 3; $i++) {
               $list = $this->generateShoppingList($consumer['income'], $consumer['dietaryRequirements']);

               foreach ($list as $product) {

                  if ($consumer->products()->where('product_id', $product['id'])->exists()) {
                     $consumer->products()->where('product_id', $product['id'])->increment('quantity');
                  } else {
                     $consumer->products()->attach($product['id'], ['quantity' => 1]);
                  }
               }
            }
      }
      return redirect(route('upload_product_data'));
   }


   // ----------- FUNCTIONS FOR DEVELOPMENT ONLY
   public function test() {
      for ($i = 0; $i < 1; $i++) { // gen 1 users
         $user = $this->generateUserData();
         // print user data
         echo "==========================================<br>";
         foreach($user as $key => $val) {
            echo "'$key' => '$val'<br>";
         }

         echo "------------------------------------------<br>";
         
         
         for ($i = 0; $i < 5; $i++) { // 5 shopping lists per user
            echo "<br><br>";
            // $list = $this->generateShoppingList($user['dietary_requirements']);
            $list = $this->generateShoppingList(20000, 'Vegan');
            foreach($list as $product) {
               echo '<br>[<a target="_blank" href="' . $product['product_link'] . '">' . $product['id'] . '</a>] ' . $product['product_name'];
            }
         }
         echo "<br>==========================================<br>";
      }
   }
}
