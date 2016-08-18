<?php

namespace app\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\CategoryProduct;
use App\Models\AlbumPhoto;
use App\Models\Section;
use App\Models\Cart;
use App\Models\Option;
use App\Models\OptionValue;
use App\Models\ProductFeature;
use App\Models\ProductVariant;
use File;
use Auth;
use App;
use Session;
use \Illuminate\Database\Eloquent\Collection;
use App\Ecommerce\helperFunctions;
use Intervention\Image\ImageManagerStatic as Image;



class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('sentinel.auth', ['except' => [
             'index',
             'show',
             'search'
         ]]);
    
    }

    public function index()
    {
        $new_products = Product::orderBy('created_at', 'desc')->take(12)->get();
        $get_best_sellers = App\OrderProduct::select('product_id', \DB::raw('COUNT(product_id) as count'))->groupBy('product_id')->orderBy('count', 'desc')->take(8)->get();
        $best_sellers = [];
        foreach ($get_best_sellers as $product) {
            $best_sellers[] = $product->product;
        }
        helperFunctions::getPageInfo($sections,$cart,$total);
        return view('site.index', compact('new_products', 'best_sellers', 'sections', 'cart', 'total'));
    }

    public function show($id)
    {
        $product = Product::find($id);

        $product_categories = $product->categories()->lists('id')->toArray();

        $similair = Category::find($product_categories[array_rand($product_categories)])->products()->whereNotIn('id', array($id))->orderByRaw("RAND()")->take(6)->get();
        helperFunctions::getPageInfo($sections,$cart,$total);
        return view('site.product', compact('sections', 'product', 'similair', 'cart', 'total'));
    }

    public function store(Request $request)
    {
        /**
    	 * Validate the submitted Data
    	 */
        $this->validate($request, [
            'name' => 'required',
            'manufacturer' => 'required',
            'price' => 'required',
            'details' => 'required',
            'quantity' => 'required',
            'categories' => 'required',
            'thumbnail' => 'required|image',
        ]);

        if ($request->hasFile('album')) {
            foreach ($request->album as $photo) {
                if ($photo && strpos($photo->getMimeType(), 'image') === false) {
                    return \Redirect()->back();
                }
            }
        }

// public Intervention\Image\Image resize (integer $width, integer $height, [Closure $callback])



// // create instance
// $img = Image::make('public/foo.jpg')

// // resize image to fixed size
// $img->resize(300, 200);

// // resize only the width of the image
// $img->resize(300, null);

// // resize only the height of the image
// $img->resize(null, 200);

// // resize the image to a width of 300 and constrain aspect ratio (auto height)
// $img->resize(300, null, function ($constraint) {
//     $constraint->aspectRatio();
// });

// // resize the image to a height of 200 and constrain aspect ratio (auto width)
// $img->resize(null, 200, function ($constraint) {
//     $constraint->aspectRatio();
// });

// // prevent possible upsizing
// $img->resize(null, 400, function ($constraint) {
//     $constraint->aspectRatio();
//     $constraint->upsize();
// });

//  $file = Request::file('file');
//         $image_name = time()."-".$file->getClientOriginalName();
//         $file->move('uploads', $image_name);
//         $image = Image::make(sprintf('uploads/%s', $image_name))->resize(200, 200)->save();
 

// In php.ini
// extension=php_fileinfo.dll


// // Resizing 340x340
// // Image::make( $file->getRealPath() )->fit(340, 340)->save('uploads/resized-image.jpg')->destroy();

// Image::make('public/default.jpg')->resize(300, 300)->save( public_path('/uploads/avatars/default_resize.jpg') );


// $image = Input::file('image');// Getting image
// $destinationPath = 'uploads'; // upload path
// $extension = $image->getClientOriginalExtension(); //Getting Image Extension
// $fileName = rand(11111,99999).'.'.$extension; // renaming image
// $img = Image::make($image);
// $medium_image = $img->resize(25,25);
// $large_image = $img->resize(50,50);
// $image->move($destinationPath, $fileName);
// $medium_image->save('uploads/medium'.$fileName);
// $large_image->save('uploads/large'.$fileName); // uploading file to given path





// $imageFile = \Image::make($imageUpload)->resize(600, 600)->stream();
// $imageFile = $imageFile->__toString();
// $filename = 'myFileName.png';
// $s3 = \Storage::disk('s3');
// $s3_response = $s3->put('/'.$filename, $imageFile, 'public');





        /**
    	 * Upload a new thumbnail
    	 */
        $dest = "uploads/product/images/";
        $catalogPath = "uploads/product/images/catalog/";
        $name = str_random(11)."_".$request->file('thumbnail')->getClientOriginalName();
        $catalogName = "catalog-".$request->file('thumbnail')->getClientOriginalName();

 

        // $image = $request->file('thumbnail')->getClientOriginalName();
        // $name = str_random(11)."_".$request->file('thumbnail')->getClientOriginalName();

        // $image->make($temppath . "original.png");

        // $image->resize(300,300);
        // $image->make($catalogPath . "catalog-" . $name);

 
     //   $catalogImage = \Image::make($catalogName)->resize(300, 300)->stream();

            $request->file('thumbnail')->move($dest, $name);
            $upload_success = $request->file('thumbnail');



            if ($upload_success) {

                    // resizing an uploaded file
                    Image::make($catalogPath, $catalogName)->resize(300, 300)->save($catalogPath, $catalogName);

                    // thumb
                    Image::make($catalogPath, $catalogName)->resize(200, 200)->save($catalogPath.'thumb_'. $catalogName);

                    // $this->article->lang = $this->getLang();
                    // $this->article->file_name = $fileName;
                    // $this->article->file_size = $fileSize;
                    // $this->article->path = $this->imgDir;
            }




   
        $product = $request->all();
        $product['thumbnail'] = "/".$dest.$name;

        $product = Product::create($product);
 
    

        /**
         * Upload Album Photos
         */
        if ($request->hasFile('album')) {
            foreach ($request->album as $photo) {
                if ($photo) {
                    $name = str_random(11)."_".$photo->getClientOriginalName();
                    $photo->move($dest, $name);
                    AlbumPhoto::create([
                        'product_id' => $product->id,
                        'photo_src' => "/".$dest.$name
                    ]);
                }
            }
        }


        /**
    	 * Linking the categories to the product
    	 */

        foreach ($request->categories as $category_id) {
            CategoryProduct::create(['category_id' => $category_id, 'product_id' => $product->id]);
        }

        /**
         * Linking the options to the product
         */

        if ($request->has('options')){
            foreach ($request->options as $option_details) {
                if (!empty($option_details['name']) && !empty($option_details['values'][0]) ) {
                    $option = Option::create([
                        'name' => $option_details['name'],
                        'product_id' => $product->id
                    ]);
                    foreach ($option_details['values'] as $value) {
                        OptionValue::create([
                            'value' => $value,
                            'option_id' => $option->id
                        ]);
                    }
                }
            }
        }
        
        if (!empty($request->attribute_name))
            {
                foreach ($request->attribute_name as $key => $item)
                {
                    $productVariant                          = new ProductVariant();
                    $productVariant->attribute_name          = $item;
                    $productVariant->product_attribute_value = $request->product_attribute_value[$key];
                    $product->productVariants()->save($productVariant);
                }
        }

        if (!empty($request->feature_name))
            {
                foreach ($request->feature_name as $feature)
                {
                    $productFeature               = new ProductFeature();
                    $productFeature->feature_name = $feature;
                    $product->productFeatures()->save($productFeature);
                }
            }


        return redirect(getLang().'/admin/products')->with([
            'flash_message' => 'Product Created Successfully'
        ]);
    }

    public function edit(Request $request, $id)
    {
        $product = Product::find($id);
        /**
    	 * Validate the submitted Data
    	 */
        $this->validate($request, [
            'name' => 'required',
            'manufacturer' => 'required',
            'price' => 'required',
            'details' => 'required',
            'quantity' => 'required',
            'categories' => 'required',
            'thumbnail' => 'image',
        ]);
        
        if ($request->hasFile('album')) {
            foreach ($request->album as $photo) {
                if ($photo && strpos($photo->getMimeType(), 'image') === false) {
                    return \Redirect()->back();
                }
            }
        }

        /**
    	 * Remove the old categories from the pivot table and maintain the reused ones
    	 */
        $added_categories = [];
        foreach ($product->categories as $category) {
            if (!in_array($category->id, $request->categories)) {
                CategoryProduct::whereProduct_id($product->id)->whereCategory_id($category->id)->delete();
            } else {
                $added_categories[] = $category->id;
            }
        }

        /**
    	 * Link the new categories to the pivot table
    	 */
        foreach ($request->categories as $category_id) {
            if (!in_array($category_id, $added_categories)) {
                CategoryProduct::create(['category_id' => $category_id, 'product_id' => $product->id]);
            }
        }

        $info = $request->all();

        /**
    	 * Upload a new thumbnail and delete the old one
    	 */
        $dest = "content/images/";
        if ($request->file('thumbnail')) {
            File::delete(public_path().$product->thumbnail);
            $name = str_random(11)."_".$request->file('thumbnail')->getClientOriginalName();
            $request->file('thumbnail')->move($dest, $name);
            $info['thumbnail'] = "/".$dest.$name;
        }
        /**
         * Upload Album Photos
         */
        if ($request->hasFile('album')) {
            foreach ($request->album as $photo) {
                if ($photo) {
                    $name = str_random(11)."_".$photo->getClientOriginalName();
                    $photo->move($dest, $name);
                    AlbumPhoto::create([
                        'product_id' => $product->id,
                        'photo_src' => "/".$dest.$name
                    ]);
                }
            }
        }

        $product->update($info);

        /**
         * Linking the options to the product
         */

        if ($request->has('options')){
            foreach ($request->options as $option_details) {
                if (!empty($option_details['name']) && !empty($option_details['values']['name'][0]) ) {
                    if (isset($option_details['id']))
                    {
                        $size = count($option_details['values']['id']);
                        Option::find($option_details['id'])->update(['name' => $option_details['name']]);
                        foreach ($option_details['values']['name'] as $key => $value) {
                            if ($key < $size)
                            {
                                OptionValue::find($option_details['values']['id'][$key])->update(['value' => $value]);
                            }
                            else
                            {
                                OptionValue::create([
                                    'value' => $value,
                                    'option_id' => $option_details['id']
                                ]);
                            }
                        }
                    }
                    else
                    {
                        $option = Option::create([
                            'name' => $option_details['name'],
                            'product_id' => $product->id
                        ]);
                        foreach ($option_details['values']['name'] as $value) {
                            if (!empty($value)) {
                                OptionValue::create([
                                    'value' => $value,
                                    'option_id' => $option->id
                                ]);
                            }
                        }
                    }
                }
            }
        }


        if (!empty($request->attribute_name))
        {
            foreach ($request->attribute_name as $key => $item)
            {
                $productVariant                          = new ProductVariant();
                $productVariant->attribute_name          = $item;
                $productVariant->product_attribute_value = $request->product_attribute_value[$key];
                $product->productVariants()->save($productVariant);
            }
        }

        if (!empty($request->feature_name))
        {
            foreach ($request->feature_name as $feature)
            {
                $productFeature               = new ProductFeature();
                $productFeature->feature_name = $feature;
                $product->productFeatures()->save($productFeature);
            }
        }



        return \Redirect()->back()->with([
            'flash_message' => 'Product Successfully Modified'
        ]);
    }

    public function delete($id)
    {
        $product = Product::find($id);

        /**
    	 * Remove the product , all its linked categories and delete the thumbnail
    	 */
        File::delete(public_path().$product->thumbnail);
        CategoryProduct::whereProduct_id($id)->delete();
        $product->delete();
        return \Redirect::back();
    }

    public function deletePhoto($id, $photo_id)
    {
        $photo = AlbumPhoto::find($photo_id);
        File::delete(public_path().$photo->photo_src);
        AlbumPhoto::destroy($photo_id);
        return \Redirect()->back();
    }

    public function deleteOption($id)
    {
        Option::destroy($id);
        return \Redirect()->back();
    }

    public function deleteOptionValue($id)
    {
        OptionValue::destroy($id);
        return \Redirect()->back();
    }

    public function search(Request $request)
    {
        if (strtoupper($request->sort) == 'NEWEST') {
            $products = Product::where('name', 'like', '%'.$request->q.'%')->orderBy('created_at', 'desc')->paginate(40);
        } elseif (strtoupper($request->sort) == 'HIGHEST') {
            $products = Product::where('name', 'like', '%'.$request->q.'%')->orderBy('price', 'desc')->paginate(40);
        } elseif (strtoupper($request->sort) == 'LOWEST') {
            $products = Product::where('name', 'like', '%'.$request->q.'%')->orderBy('price', 'asc')->paginate(40);
        } else {
            $products = Product::where('name', 'like', '%'.$request->q.'%')->paginate(40);
        }
        helperFunctions::getPageInfo($sections,$cart,$total);
        $query = $request->q;
        return view('site.search', compact('sections', 'cart', 'total', 'products', 'query'));
    }
}
